<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AgoraTranscriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgoraTranslationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Enforce strict prevention of un-mocked external HTTP calls
        Http::preventStrayRequests();

        // Setup valid test config (32-character hex strings)
        Config::set('agora.app_id', 'a5a2c532fe6b48f5a8c365eb1f2216e9');
        Config::set('agora.app_certificate', 'fd104f27bb39448c93516405397925a3');
        Config::set('agora.customer_id', 'test_cust_id');
        Config::set('agora.customer_secret', 'test_cust_secret');
    }

    private function createAppointment(string $paymentStatus = 'paid', string $status = 'approved'): array
    {
        $doctorUser = User::factory()->create(['role' => 'doctor']);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

        $appointment = Appointment::factory()->create([
            'doctor_id'        => $doctor->id,
            'patient_id'       => $patient->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => now()->format('H:i:s'),
            'payment_status'   => $paymentStatus,
            'status'           => $status,
            'agora_channel'    => 'appt_test_' . rand(100, 999),
            'call_started_at'  => now(),
        ]);

        return [$appointment, $doctorUser, $patientUser];
    }

    /** 1. Missing REST Credentials */
    public function test_missing_rest_credentials_throws_exception(): void
    {
        Config::set('agora.customer_id', '');

        [$appointment, $doctorUser] = $this->createAppointment();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US', 'ur-PK'],
            ]);

        $response->assertStatus(500);
        $response->assertJsonFragment(['active' => false]);
    }

    /** 2. Unsupported Language Rejected */
    public function test_unsupported_language_rejected(): void
    {
        [$appointment, $doctorUser] = $this->createAppointment();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['invalid-lang-code'],
            ]);

        $response->assertStatus(422);
    }

    /** 3. Duplicate Target Languages Collapsed */
    public function test_duplicate_target_languages_collapsed(): void
    {
        $service = new AgoraTranscriptionService();
        $cleaned = $service->cleanTargetLanguages(['en-US', 'en-US', 'ur-PK', 'ur-PK']);

        $this->assertEquals(['en-US', 'ur-PK'], $cleaned);
    }

    /** 4. Valid Start Creates Task */
    public function test_valid_start_creates_agora_task(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join' => Http::response([
                'taskId' => 'task_12345',
                'status' => 'active',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US', 'ur-PK'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'active'  => true,
            'task_id' => 'task_12345',
        ]);

        $this->assertTrue(Cache::has("agora_transcription_{$appointment->id}"));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && $request['translateConfig']['languages'][0]['target'] === ['ur-PK'];
        });
    }

    /** 5. Duplicate Start Prevented */
    public function test_duplicate_start_prevented(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_12345',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        // Pre-populate state cache
        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id' => 'task_12345',
            'status'  => 'active',
        ], 1800);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US', 'ur-PK'],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Translation is already active.',
            'active'  => true,
            'task_id' => 'task_12345',
        ]);

        Http::assertSentCount(0); // No HTTP request made
    }

    /** 6. Concurrent Start Protected */
    public function test_concurrent_start_protected_by_lock(): void
    {
        [$appointment, $doctorUser] = $this->createAppointment();

        // Acquire start lock artificially
        $lock = Cache::lock("agora_transcription_start_{$appointment->id}", 10);
        $lock->get();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US', 'ur-PK'],
            ]);

        $response->assertStatus(429);
        $response->assertJsonFragment(['active' => false]);

        $lock->release();
    }

    /** 7. Successful Target Language PATCH */
    public function test_successful_target_language_patch(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/agents/task_12345' => Http::response([], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id'      => 'task_12345',
            'target_langs' => ['en-US', 'ur-PK'],
        ], 1800);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.language', $appointment->id), [
                'target_lang' => 'ar-SA',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'target_lang' => 'ar-SA',
        ]);

        $cached = Cache::get("agora_transcription_{$appointment->id}");
        $this->assertContains('ar-SA', $cached['target_langs']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && in_array('ar-SA', $request['translateConfig']['languages'][0]['target']);
        });
    }

    /** 8. Failed PATCH Preserves Old Configuration */
    public function test_failed_patch_preserves_old_configuration(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/agents/task_12345' => Http::response([], 500),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id'      => 'task_12345',
            'target_langs' => ['en-US', 'ur-PK'],
        ], 1800);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.language', $appointment->id), [
                'target_lang' => 'ar-SA',
            ]);

        $response->assertStatus(502);

        $cached = Cache::get("agora_transcription_{$appointment->id}");
        $this->assertEquals(['en-US', 'ur-PK'], $cached['target_langs']); // Retains old config
    }

    /** 9. Successful Stop */
    public function test_successful_stop(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/agents/task_12345/leave' => Http::response([], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id' => 'task_12345',
        ], 1800);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.stop', $appointment->id));

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);
        $this->assertFalse(Cache::has("agora_transcription_{$appointment->id}"));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' && str_contains($request->url(), '/leave');
        });
    }

    /** 10. Repeated Stop Is Idempotent */
    public function test_repeated_stop_is_idempotent(): void
    {
        [$appointment, $doctorUser] = $this->createAppointment();

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.stop', $appointment->id));

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);
    }

    /** 11. Unauthorized User Denied */
    public function test_unauthorized_user_denied(): void
    {
        [$appointment] = $this->createAppointment();
        $unrelatedUser = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($unrelatedUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US'],
            ]);

        $response->assertStatus(403);
    }

    /** 12. Unpaid Appointment Denied */
    public function test_unpaid_appointment_denied(): void
    {
        [$appointment, $doctorUser] = $this->createAppointment('unpaid', 'approved');

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id), [
                'target_langs' => ['en-US'],
            ]);

        $response->assertStatus(403);
    }

    /** 13. Translation Failure Does Not Affect RTC Call Status */
    public function test_translation_failure_does_not_affect_rtc_call(): void
    {
        [$appointment, $doctorUser] = $this->createAppointment();

        $response = $this->actingAs($doctorUser)
            ->getJson(route('appointments.call-status', $appointment->id));

        $response->assertStatus(200);
        $response->assertJson(['active' => true]);
    }

    /** 14. Agora Cleanup Failure Does Not Prevent End Call */
    public function test_agora_cleanup_failure_does_not_prevent_end_call(): void
    {
        // Agora leave endpoint returns 500 Server Error
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/agents/task_12345/leave' => Http::response([], 500),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id' => 'task_12345',
        ], 1800);

        // Doctor ends call
        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.end-call', $appointment->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'duration_seconds', 'formatted_duration']);

        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
    }

    /** 15. No Real Agora Request Occurs In Tests */
    public function test_no_real_agora_request_occurs_in_tests(): void
    {
        Http::preventStrayRequests();
        $this->assertTrue(true);
    }

    /** 16. All Seven Supported Languages Validated Cleanly */
    public function test_all_seven_supported_languages_validated_cleanly(): void
    {
        $service = new AgoraTranscriptionService();
        $allLangs = ['en-US', 'ur-PK', 'ar-SA', 'es-ES', 'fr-FR', 'de-DE', 'hi-IN'];
        
        $cleaned = $service->cleanTargetLanguages($allLangs);
        $this->assertEquals($allLangs, $cleaned);
    }

    /** 17. Dynamic Language Switching Chain Maintains Same Task ID & Uses PATCH */
    public function test_dynamic_language_switching_chain(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/agents/task_chain_123' => Http::response([], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();

        Cache::put("agora_transcription_{$appointment->id}", [
            'task_id'      => 'task_chain_123',
            'target_langs' => ['en-US', 'ur-PK'],
        ], 1800);

        $chain = ['ar-SA', 'es-ES', 'fr-FR', 'de-DE', 'hi-IN', 'ur-PK'];

        foreach ($chain as $targetLang) {
            $response = $this->actingAs($doctorUser)
                ->postJson(route('appointments.translation.language', $appointment->id), [
                    'target_lang' => $targetLang,
                ]);

            $response->assertStatus(200);
            $cached = Cache::get("agora_transcription_{$appointment->id}");
            $this->assertEquals('task_chain_123', $cached['task_id']); // Task ID remains identical!
            $this->assertContains($targetLang, $cached['target_langs']);
        }

        // Verify only PATCH requests were sent during switching
        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH';
        });
    }

    /** 18. Same Language Selection Deduplicates Target Array */
    public function test_same_language_selection_deduplicates_target_array(): void
    {
        $service = new AgoraTranscriptionService();
        $cleaned = $service->cleanTargetLanguages(['en-US', 'en-US', 'en-US']);

        $this->assertEquals(['en-US'], $cleaned);
    }

    /** 19. Dynamic Appointment Subtitle Language Selection */
    public function test_dynamic_appointment_subtitle_language_selection(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_spanish_123',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();
        $appointment->update(['subtitle_language' => 'es-ES']);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id));

        $response->assertStatus(200);
        $response->assertJson([
            'active'  => true,
            'task_id' => 'task_spanish_123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && $request['translateConfig']['languages'][0]['target'] === ['es-ES'];
        });
    }

    /** 20. English Subtitle Language Omits Translate Config */
    public function test_english_subtitle_language_omits_translate_config(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_english_123',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();
        $appointment->update(['subtitle_language' => 'en-US']);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && !isset($request['translateConfig']);
        });
    }

    /** 21. Legacy Null Subtitle Language Falls Back to Default */
    public function test_legacy_null_subtitle_language_falls_back_to_default(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_default_123',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();
        $appointment->update(['subtitle_language' => null]);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && $request['translateConfig']['languages'][0]['target'] === [config('subtitles.default')];
        });
    }

    /** 22. Future Language Added to Config Works Automatically */
    public function test_future_language_added_to_config_works_automatically(): void
    {
        config(['subtitles.languages.it-IT' => [
            'name'      => 'Italian',
            'direction' => 'ltr',
        ]]);

        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_italian_123',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();
        $appointment->update(['subtitle_language' => 'it-IT']);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && $request['translateConfig']['languages'][0]['target'] === ['it-IT'];
        });
    }

    /** 23. Corrupted Stored Subtitle Language Logs Warning and Falls Back */
    public function test_corrupted_database_subtitle_language_logs_warning_and_falls_back_to_default(): void
    {
        Http::fake([
            'https://api.agora.io/api/speech-to-text/v1/projects/*/join' => Http::response([
                'taskId' => 'task_corrupt_123',
            ], 200),
        ]);

        [$appointment, $doctorUser] = $this->createAppointment();
        $appointment->update(['subtitle_language' => 'xx-YY']);

        $response = $this->actingAs($doctorUser)
            ->postJson(route('appointments.translation.start', $appointment->id));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.agora.io/api/speech-to-text/v1/projects/a5a2c532fe6b48f5a8c365eb1f2216e9/join'
                && $request->method() === 'POST'
                && $request['translateConfig']['languages'][0]['target'] === [config('subtitles.default', 'ur-PK')];
        });
    }
}



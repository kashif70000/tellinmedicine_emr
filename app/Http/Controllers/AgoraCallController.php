<?php

namespace App\Http\Controllers;

use App\Helpers\AgoraTokenBuilder;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\AgoraTranscriptionService;
use Carbon\Carbon;


class AgoraCallController extends Controller
{
    private const CALL_DURATION_SECONDS = 1800;

    // ---------------------------------------------------------------
    // POST /doctor/appointments/{id}/start-call
    // ---------------------------------------------------------------
    public function startCall(Request $request, int $id)
    {
        $user = Auth::user();

        // Only doctors
        if ($user->role !== 'doctor') {
            abort(403, 'Only doctors can start a call.');
        }

        $doctor = $user->doctor;
        if (! $doctor) {
            abort(403, 'Doctor profile not found.');
        }

        $appointment = Appointment::findOrFail($id);

        if ((int) $appointment->doctor_id !== (int) $doctor->id) {
            abort(403, 'This appointment does not belong to you.');
        }

        if ($appointment->payment_status !== 'paid') {
            return back()->with('error', 'Call cannot be started: appointment fee is unpaid.');
        }

        if ($appointment->status !== 'approved' && $appointment->status !== 'completed') {
            return back()->with('error', 'Call cannot be started: appointment is not approved.');
        }

        $scheduledAt = Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' .
            Carbon::parse($appointment->appointment_time)->format('H:i:s')
        );

        $now = now();
        $earliestStart = $scheduledAt->copy()->subMinutes(5);
        $sessionEndTime = $this->getScheduledSessionEnd($appointment);

        if ($now->lt($earliestStart)) {
            return back()->with('error', 'Call cannot be started before the scheduled appointment time. Please wait until ' . $earliestStart->format('g:i A'));
        }

        if ($now->gt($sessionEndTime)) {
            return back()->with('error', 'This appointment session has expired. The 30-minute window ended at ' . $sessionEndTime->format('g:i A'));
        }

        $channel = $appointment->agora_channel ?? ('appt_' . $id);
        $expiredTs = $sessionEndTime->timestamp;

        $token = $this->generateToken($channel, $user->id, AgoraTokenBuilder::ROLE_PUBLISHER, $expiredTs);

        $isFirstStart = ! $appointment->call_started_at;
        $callStartedAt = $appointment->call_started_at ?? Carbon::now();

        $appointment->update([
            'agora_channel'   => $channel,
            'agora_uid'       => (string) $user->id,
            'call_started_at' => $callStartedAt,
            'status'          => 'approved',
        ]);

        if ($isFirstStart && optional(optional($appointment->patient)->user)) {
            try {
                $appointment->patient->user->notify(new \App\Notifications\DoctorStartedCallNotification($appointment));
            } catch (\Throwable $e) {
                Log::warning('DoctorStartedCallNotification failed: ' . $e->getMessage());
            }
        }

        if ($request->expectsJson()) {
            $appId = config('agora.app_id');
            return response()->json([
                'channel_name' => $channel,
                'token'        => $token,
                'app_id'       => $appId,
                'uid'          => $user->id,
                'expires_at'   => $expiredTs,
            ]);
        }

        return redirect()->route('appointments.call', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // GET /appointments/{id}/join-call
    // ---------------------------------------------------------------
    public function joinCall(int $id)
    {
        $user        = Auth::user();
        $appointment = Appointment::with(['doctor.user', 'patient.user', 'prescription'])->findOrFail($id);

        $isDoctor = $user->role === 'doctor'
            && (int) optional($user->doctor)->id === (int) $appointment->doctor_id;

        $isPatient = $user->role === 'patient'
            && (int) optional($user->patient)->id === (int) $appointment->patient_id;

        if (! $isDoctor && ! $isPatient) {
            abort(403, 'You are not part of this appointment.');
        }

        if ($appointment->payment_status !== 'paid') {
            return back()->with('error', 'This appointment has not been paid.');
        }

        if (! $appointment->call_started_at) {
            return back()->with('error', 'The call has not been started by the doctor yet.');
        }

        if ($appointment->status === 'completed') {
            return back()->with('error', 'This video consultation has already been completed.');
        }

        if (in_array($appointment->status, ['cancelled', 'rejected'], true)) {
            return back()->with('error', 'This appointment has been cancelled.');
        }

        if ($appointment->status !== 'approved') {
            return back()->with('error', 'The call cannot be joined as the appointment is not approved.');
        }

        if ($this->completeExpiredCall($appointment) && $appointment->status === 'completed') {
            return back()->with('error', 'This call session has expired.');
        }

        $channel   = $appointment->agora_channel;
        $sessionEnd = $this->getScheduledSessionEnd($appointment);
        $expiredTs = $sessionEnd->timestamp;
        $token     = $this->generateToken($channel, $user->id, AgoraTokenBuilder::ROLE_PUBLISHER, $expiredTs);

        $appId = config('agora.app_id');

        $supportedLanguages = config('subtitles.languages', []);
        $defaultLanguage    = config('subtitles.default', 'ur-PK');

        $subtitleLang = $appointment->subtitle_language;
        if (!$subtitleLang || !array_key_exists($subtitleLang, $supportedLanguages)) {
            $subtitleLang = $defaultLanguage;
        }

        $langConfig = $supportedLanguages[$subtitleLang] ?? [
            'name'      => 'Urdu',
            'direction' => 'rtl',
        ];

        $callData = [
            'channel_name'       => $channel,
            'token'              => $token,
            'app_id'             => $appId,
            'uid'                => $user->id,
            'expires_at'         => $expiredTs,
            'call_started_at_ts' => $appointment->call_started_at->timestamp,
            'is_doctor'          => $isDoctor,
            'subtitle_language'  => $subtitleLang,
            'subtitle_direction' => $langConfig['direction'] ?? 'ltr',
            'subtitle_lang_name' => $langConfig['name'] ?? 'Language',
        ];

        return view('appointment.video-call', compact('appointment', 'callData'));
    }

    // ---------------------------------------------------------------
    // POST /appointments/{id}/end-call
    // ---------------------------------------------------------------
    public function endCall(Request $request, int $id)
    {
        $user = Auth::user();

        if ($user->role !== 'doctor') {
            abort(403, 'Only doctors can end a call.'); 
        }

        $appointment = Appointment::findOrFail($id);

        if ((int) $appointment->doctor_id !== (int) optional($user->doctor)->id) {
            abort(403, 'This appointment does not belong to you.');
        }

        if ($appointment->status === 'completed') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message'            => 'Call already ended.',
                    'duration_seconds'   => $appointment->duration_seconds,
                    'formatted_duration' => $appointment->formatted_duration,
                ]);
            }
            return redirect()->route('appointments.show', $appointment);
        }

        $completedAt = Carbon::now();
        $startedAt = $appointment->call_started_at ?? $completedAt;
        $seconds = min(1800, max(0, $completedAt->timestamp - $startedAt->timestamp));

        $appointment->update([
            'status'           => 'completed',
            'completed_at'     => $completedAt,
            'duration_seconds' => $seconds,
        ]);

        $this->cleanupActiveTranslation($appointment->id);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message'            => 'Call ended. Appointment marked as completed.',
                'duration_seconds'   => $seconds,
                'formatted_duration' => $appointment->formatted_duration,
            ]);
        }

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Video call ended. Appointment marked as completed.');
    }

    // ---------------------------------------------------------------
    // GET /appointments/{id}/call-status (JSON, polling endpoint)
    // ---------------------------------------------------------------
    public function callStatus(int $id)
    {
        $user        = Auth::user();
        $appointment = Appointment::findOrFail($id);

        $isDoctor = $user->role === 'doctor'
            && (int) optional($user->doctor)->id === (int) $appointment->doctor_id;

        $isPatient = $user->role === 'patient'
            && (int) optional($user->patient)->id === (int) $appointment->patient_id;

        if (! $isDoctor && ! $isPatient) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $expiresAt = $this->getScheduledSessionEnd($appointment)->timestamp;
        $expired = $this->completeExpiredCall($appointment);

        $isActive = ! $expired
            && $appointment->status === 'approved'
            && $appointment->call_started_at
            && Carbon::now()->lte($this->getScheduledSessionEnd($appointment));

        return response()->json([
            'active'             => $isActive,
            'status'             => $appointment->status,
            'expires_at'         => $expiresAt,
            'call_started_at_ts' => $appointment->call_started_at ? $appointment->call_started_at->timestamp : null,
            'duration_seconds'   => $appointment->duration_seconds,
            'formatted_duration' => $appointment->formatted_duration,
            'server_time'        => time(),
        ]);
    }

    // ---------------------------------------------------------------
    // GET /patient/active-call-check (JSON, header notification poll)
    // ---------------------------------------------------------------
    public function activeCallCheck()
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'patient' || ! $user->patient) {
            return response()->json(['active_call' => null]);
        }

        $activeAppointment = Appointment::with('doctor.user')
            ->where('patient_id', $user->patient->id)
            ->where('status', 'approved')
            ->where('payment_status', 'paid')
            ->whereNotNull('call_started_at')
            ->where('call_started_at', '>=', Carbon::now()->subMinutes(30))
            ->first();

        if (! $activeAppointment) {
            return response()->json(['active_call' => null]);
        }

        return response()->json([
            'active_call' => [
                'id'          => $activeAppointment->id,
                'doctor_name' => optional(optional($activeAppointment->doctor)->user)->name ?? 'Doctor',
                'join_url'    => route('appointments.call', $activeAppointment->id),
            ],
        ]);
    }

    private function generateToken(string $channel, int $uid, int $role, int $expiredTs): string
    {
        $appId          = config('agora.app_id');
        $appCertificate = config('agora.app_certificate');

        if (empty($appId) || empty($appCertificate)) {
            Log::error('Agora credentials missing', [
                'app_id'          => $appId,
                'app_certificate' => !empty($appCertificate) ? 'SET' : 'EMPTY',
            ]);
            throw new \RuntimeException('Agora credentials are not configured in .env file. Please set AGORA_APP_ID and AGORA_APP_CERTIFICATE.');
        }

        return AgoraTokenBuilder::buildTokenWithUid(
            $appId,
            $appCertificate,
            $channel,
            $uid,
            $role,
            $expiredTs
        );
    }

    private function completeExpiredCall(Appointment $appointment): bool
    {
        if (! $appointment->call_started_at) {
            return false;
        }

        $sessionEnd = $this->getScheduledSessionEnd($appointment);
        $expired = Carbon::now()->gt($sessionEnd);

        if ($expired && $appointment->status === 'approved') {
            $seconds = min(1800, max(0, $sessionEnd->timestamp - $appointment->call_started_at->timestamp));
            $appointment->update([
                'status'           => 'completed',
                'completed_at'     => $sessionEnd,
                'duration_seconds' => $seconds,
            ]);
            $this->cleanupActiveTranslation($appointment->id);
        }

        return $expired;
    }

    private function getScheduledSessionEnd(Appointment $appointment)
    {
        return Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' .
            Carbon::parse($appointment->appointment_time)->format('H:i:s')
        )->addMinutes(30);
    }

    private function cleanupActiveTranslation(int $appointmentId): void
    {
        try {
            $cacheKey = "agora_transcription_{$appointmentId}";
            $taskData = Cache::get($cacheKey);
            if ($taskData && !empty($taskData['task_id'])) {
                app(AgoraTranscriptionService::class)->stopTranslation($taskData['task_id']);
            }
            Cache::forget($cacheKey);
        } catch (\Throwable $e) {
            Log::warning('cleanupActiveTranslation error: ' . $e->getMessage());
        }
    }
}

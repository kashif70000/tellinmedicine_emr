<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PatientRegistrationEmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_patient_registration_sends_email_verification_notification(): void
    {
        Notification::fake();
        $this->withoutMiddleware();

        $response = $this->post(route('register.patient.store'), [
            'name' => 'Test Patient',
            'email' => 'patient@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
            'age' => 30,
            'gender' => 'male',
            'blood_group' => 'A+',
            'address' => 'Test address',
        ]);

        $user = User::where('email', 'patient@example.com')->first();

        $response->assertRedirect(route('login'));
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}

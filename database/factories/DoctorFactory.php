<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        $specializations = [
            'Cardiology',
            'Neurology',
            'Orthopedics',
            'Dermatology',
            'Pediatrics',
            'Oncology',
            'Gastroenterology',
            'Psychiatry',
            'Pulmonology',
            'Ophthalmology',
            'General Surgery',
            'Nephrology',
        ];

        $clinics = [
            'St. Jude Medical Center',
            'City Health Specialty Clinic',
            'Apex Health Institute',
            'Metro Care Hospital',
            'Evergreen Medical Complex',
            'Grace Medical Center',
            'Horizon Care Clinic',
            'Sunrise General Hospital',
        ];

        return [
            'user_id' => User::factory()->state(['role' => 'doctor']),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement($specializations),
            'experience' => fake()->numberBetween(2, 28),
            'fees' => fake()->randomFloat(2, 500, 3000),
            'clinic_name' => fake()->randomElement($clinics),
            'address' => fake()->address(),
            'is_verified' => true,
            'verification_status' => 'approved',
            'certificate_path' => 'certificates/medical_license_' . fake()->numberBetween(100, 999) . '.pdf',
            'license_number' => 'PMC-' . fake()->numberBetween(10000, 99999) . '-REG',
            'ai_result' => [
                'document_valid' => true,
                'license_match' => true,
                'confidence_score' => 0.98,
                'verified_at' => now()->toIso8601String(),
            ],
            'rating_avg' => fake()->randomFloat(2, 4.0, 5.0),
        ];
    }
}

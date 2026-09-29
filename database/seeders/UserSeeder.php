<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Create Admin Account
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $adminUser->id],
            [
                'permissions' => ['manage_users', 'manage_doctors', 'manage_reports', 'manage_billing', 'system_settings'],
            ]
        );

        // 2. Fixed Demo Doctors (5 Doctors)
        $demoDoctorsData = [
            [
                'name' => 'Dr. Alexander Fleming',
                'email' => 'doctor1@example.com',
                'specialization' => 'Cardiology',
                'experience' => 15,
                'fees' => 1500.00,
                'clinic_name' => 'HeartCare Cardiology Center',
                'address' => 'Suite 401, Medical Tower, Downtown',
                'phone' => '+1 (555) 234-5678',
                'license' => 'PMC-10021-REG',
            ],
            [
                'name' => 'Dr. Eleanor Vance',
                'email' => 'doctor2@example.com',
                'specialization' => 'Neurology',
                'experience' => 12,
                'fees' => 1800.00,
                'clinic_name' => 'NeuroHealth Brain Institute',
                'address' => 'Floor 6, St. Jude Hospital, City Center',
                'phone' => '+1 (555) 345-6789',
                'license' => 'PMC-10022-REG',
            ],
            [
                'name' => 'Dr. Marcus Thorne',
                'email' => 'doctor3@example.com',
                'specialization' => 'Orthopedics',
                'experience' => 18,
                'fees' => 1200.00,
                'clinic_name' => 'Apex Bone & Joint Clinic',
                'address' => '120 West Parkway Ave, Sector 4',
                'phone' => '+1 (555) 456-7890',
                'license' => 'PMC-10023-REG',
            ],
            [
                'name' => 'Dr. Sophia Reyes',
                'email' => 'doctor4@example.com',
                'specialization' => 'Dermatology',
                'experience' => 8,
                'fees' => 1000.00,
                'clinic_name' => 'ClearSkin Skin & Laser Center',
                'address' => 'Building B, Commercial Plaza',
                'phone' => '+1 (555) 567-8901',
                'license' => 'PMC-10024-REG',
            ],
            [
                'name' => 'Dr. William Mercer',
                'email' => 'doctor5@example.com',
                'specialization' => 'Pediatrics',
                'experience' => 20,
                'fees' => 1400.00,
                'clinic_name' => 'Little Sprouts Children Clinic',
                'address' => '45 Sunshine Boulevard',
                'phone' => '+1 (555) 678-9012',
                'license' => 'PMC-10025-REG',
            ],
        ];

        foreach ($demoDoctorsData as $dData) {
            $user = User::firstOrCreate(
                ['email' => $dData['email']],
                [
                    'name' => $dData['name'],
                    'password' => $password,
                    'role' => 'doctor',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            Doctor::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $dData['specialization'],
                    'experience' => $dData['experience'],
                    'fees' => $dData['fees'],
                    'clinic_name' => $dData['clinic_name'],
                    'address' => $dData['address'],
                    'phone' => $dData['phone'],
                    'license_number' => $dData['license'],
                    'is_verified' => true,
                    'verification_status' => 'approved',
                    'rating_avg' => 4.80,
                    'certificate_path' => 'certificates/demo_license.pdf',
                    'ai_result' => [
                        'document_valid' => true,
                        'confidence_score' => 0.99,
                        'verified_at' => now()->toIso8601String(),
                    ],
                ]
            );
        }

        // 3. Additional Generated Doctors (15 Doctors)
        Doctor::factory()->count(15)->create();

        // 4. Fixed Demo Patients (10 Patients)
        $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-'];
        for ($i = 1; $i <= 10; $i++) {
            $email = "patient{$i}@example.com";
            $patientUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => fake()->name(),
                    'password' => $password,
                    'role' => 'patient',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $dob = fake()->dateTimeBetween('-60 years', '-20 years');

            Patient::firstOrCreate(
                ['user_id' => $patientUser->id],
                [
                    'phone' => fake()->phoneNumber(),
                    'dob' => $dob->format('Y-m-d'),
                    'age' => now()->diffInYears($dob),
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'blood_group' => $bloodGroups[($i - 1) % count($bloodGroups)],
                    'address' => fake()->address(),
                    'is_payment_method_verified' => true,
                    'is_verified' => true,
                ]
            );
        }

        // 5. Additional Generated Patients (30 Patients)
        Patient::factory()->count(30)->create();
    }
}

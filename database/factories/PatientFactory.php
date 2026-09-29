<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-75 years', '-18 years');
        $age = now()->diffInYears($dob);

        return [
            'user_id' => User::factory()->state(['role' => 'patient']),
            'phone' => fake()->phoneNumber(),
            'dob' => $dob->format('Y-m-d'),
            'age' => $age,
            'gender' => fake()->randomElement(['male', 'female']),
            'blood_group' => fake()->randomElement(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']),
            'address' => fake()->address(),
            'is_payment_method_verified' => fake()->boolean(80),
            'is_verified' => true,
        ];
    }
}

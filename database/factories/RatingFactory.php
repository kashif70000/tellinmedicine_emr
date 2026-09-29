<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        $reviews = [
            'Extremely professional doctor! Thoroughly explained the treatment plan and listened patiently.',
            'Great consultation experience. Highly knowledgeable and caring.',
            'Punctual and friendly. The prescribed medicine helped me recover quickly.',
            'Clean clinic environment and very supportive consultation.',
            'Very patient-centric approach. Answered all my questions clearly.',
            'Satisfactory consultation. Would recommend to friends and family.',
            'Outstanding service and expertise. Feeling much better after the visit.',
        ];

        return [
            'appointment_id' => Appointment::factory(),
            'doctor_id' => Doctor::factory(),
            'patient_id' => Patient::factory(),
            'rating' => fake()->numberBetween(4, 5),
            'review' => fake()->randomElement($reviews),
        ];
    }
}

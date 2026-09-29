<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Rating;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    public function run(): void
    {
        $completedAppointments = Appointment::where('status', 'completed')->get();

        $reviews = [
            'Extremely professional doctor! Thoroughly explained the treatment plan and listened patiently.',
            'Great consultation experience. Highly knowledgeable and caring.',
            'Punctual and friendly. The prescribed medicine helped me recover quickly.',
            'Clean clinic environment and very supportive online consultation.',
            'Very patient-centric approach. Answered all my questions clearly.',
            'Satisfactory consultation. Would recommend to friends and family.',
            'Outstanding medical expertise. Feeling much better after following the prescribed regimen.',
            'Prompt response and clear diagnosis. Very satisfied with the care received.',
        ];

        foreach ($completedAppointments as $index => $appointment) {
            // Avoid duplicate rating per appointment
            if (Rating::where('appointment_id', $appointment->id)->exists()) {
                continue;
            }

            Rating::create([
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'rating' => rand(4, 5),
                'review' => $reviews[$index % count($reviews)],
            ]);
        }
    }
}

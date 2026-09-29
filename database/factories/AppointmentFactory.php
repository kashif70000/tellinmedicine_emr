<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'approved', 'completed', 'cancelled']);
        $date = fake()->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d');
        $time = fake()->randomElement(['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']);

        $isCompleted = ($status === 'completed');
        $isPaid = ($isCompleted || fake()->boolean(60));

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'appointment_date' => $date,
            'appointment_time' => $time,
            'status' => $status,
            'fee_snapshot' => fake()->randomFloat(2, 500, 2500),
            'notes' => fake()->sentence(),
            'payment_status' => $isPaid ? 'paid' : 'unpaid',
            'paid_at' => $isPaid ? now()->subDays(rand(1, 30)) : null,
            'payout_status' => $isCompleted ? 'paid' : 'pending',
            'refunded_at' => ($status === 'cancelled' && $isPaid) ? now() : null,
            'call_started_at' => $isCompleted ? now()->subDays(rand(1, 30)) : null,
            'completed_at' => $isCompleted ? now()->subDays(rand(1, 30)) : null,
            'agora_channel' => 'room_' . fake()->uuid(),
            'agora_uid' => (string) fake()->numberBetween(100000, 999999),
        ];
    }
}

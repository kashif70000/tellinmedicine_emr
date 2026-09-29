<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorScheduleFactory extends Factory
{
    protected $model = DoctorSchedule::class;

    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'available_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }
}

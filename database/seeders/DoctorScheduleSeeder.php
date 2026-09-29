<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Seeder;

class DoctorScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = Doctor::all();
        $timeSlots = [
            ['09:00:00', '12:00:00'],
            ['14:00:00', '18:00:00'],
        ];

        foreach ($doctors as $doctor) {
            for ($dayOffset = 0; $dayOffset <= 14; $dayOffset++) {
                $availableDate = now()->addDays($dayOffset)->format('Y-m-d');

                foreach ($timeSlots as $slot) {
                    DoctorSchedule::firstOrCreate(
                        [
                            'doctor_id' => $doctor->id,
                            'available_date' => $availableDate,
                            'start_time' => $slot[0],
                        ],
                        [
                            'end_time' => $slot[1],
                        ]
                    );
                }
            }
        }
    }
}

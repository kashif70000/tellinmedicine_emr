<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = Doctor::all();
        $patients = Patient::all();

        if ($doctors->isEmpty() || $patients->isEmpty()) {
            return;
        }

        $times = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
        $statuses = ['completed', 'completed', 'completed', 'approved', 'approved', 'pending', 'cancelled'];

        // Track used doctor slots to respect unique constraint ['doctor_id', 'appointment_date', 'appointment_time']
        $usedSlots = [];

        // Generate ~120 appointments
        for ($i = 0; $i < 120; $i++) {
            $doctor = $doctors->random();
            $patient = $patients->random();
            $status = $statuses[$i % count($statuses)];

            // Pick a date: completed/cancelled in past (-45 to -1 days), approved/pending in future (+1 to +30 days)
            if (in_array($status, ['completed', 'cancelled'])) {
                $dateObj = now()->subDays(rand(1, 45));
            } else {
                $dateObj = now()->addDays(rand(1, 30));
            }

            $date = $dateObj->format('Y-m-d');
            $time = $times[rand(0, count($times) - 1)];

            $slotKey = "{$doctor->id}_{$date}_{$time}";
            if (isset($usedSlots[$slotKey])) {
                continue; // Skip duplicate slot
            }
            $usedSlots[$slotKey] = true;

            $isCompleted = ($status === 'completed');
            $isPaid = ($isCompleted || in_array($status, ['approved']) || ($status === 'pending' && rand(0, 1)));

            $callStartedAt = $isCompleted ? (clone $dateObj)->setTime(rand(9, 16), rand(0, 59)) : null;
            $completedAt = $isCompleted ? (clone $callStartedAt)->addMinutes(rand(15, 30)) : null;
            $paidAt = $isPaid ? (clone $dateObj)->subDays(rand(1, 3)) : null;
            $refundedAt = ($status === 'cancelled' && $isPaid) ? (clone $dateObj)->addHours(2) : null;

            Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'status' => $status,
                'fee_snapshot' => $doctor->fees,
                'notes' => fake()->randomElement([
                    'Patient experiencing persistent fatigue and mild dizziness.',
                    'Routine follow-up consultation regarding blood pressure regulation.',
                    'Follow-up visit for laboratory test results evaluation.',
                    'Initial consultation for chronic joint stiffness and pain.',
                    'Post-treatment health assessment and prescription review.',
                ]),
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
                'paid_at' => $paidAt,
                'payout_status' => $isCompleted ? 'paid' : 'pending',
                'refunded_at' => $refundedAt,
                'call_started_at' => $callStartedAt,
                'completed_at' => $completedAt,
                'agora_channel' => 'room_' . Str::random(12),
                'agora_uid' => (string) rand(100000, 999999),
            ]);
        }
    }
}

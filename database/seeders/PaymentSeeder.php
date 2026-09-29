<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $appointments = Appointment::whereIn('payment_status', ['paid', 'unpaid'])->get();
        $methods = ['card', 'card', 'online', 'cash'];

        foreach ($appointments as $appointment) {
            // Avoid duplicate payments per appointment
            if (Payment::where('appointment_id', $appointment->id)->exists()) {
                continue;
            }

            $isPaid = ($appointment->payment_status === 'paid');
            $status = $isPaid ? 'paid' : 'unpaid';
            $method = $methods[rand(0, count($methods) - 1)];

            Payment::create([
                'appointment_id' => $appointment->id,
                'payment_intent_id' => $isPaid ? 'pi_' . Str::lower(Str::random(24)) : null,
                'amount' => $appointment->fee_snapshot ?? 1000.00,
                'status' => $status,
                'method' => $method,
                'transaction_id' => $isPaid ? 'TXN-' . strtoupper(Str::random(10)) : null,
            ]);
        }
    }
}

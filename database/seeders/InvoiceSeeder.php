<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $payments = Payment::with('appointment')->get();
        $counter = 1;
        $monthPrefix = now()->format('Ym');

        foreach ($payments as $payment) {
            if (Invoice::where('payment_id', $payment->id)->exists()) {
                continue;
            }

            $appointment = $payment->appointment;
            if (! $appointment) {
                continue;
            }

            $invoiceNumber = "INV-{$monthPrefix}-" . str_pad((string) $counter++, 5, '0', STR_PAD_LEFT);
            $isPaid = ($payment->status === 'paid');

            Invoice::create([
                'payment_id' => $payment->id,
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $payment->amount,
                'issued_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : now()->format('Y-m-d'),
                'status' => $isPaid ? 'paid' : 'pending',
                'email_sent' => $isPaid,
                'emailed_at' => $isPaid ? now()->subDays(rand(1, 15)) : null,
            ]);
        }
    }
}

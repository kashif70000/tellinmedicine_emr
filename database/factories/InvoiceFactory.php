<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['paid', 'pending']);
        $emailSent = fake()->boolean(70);

        return [
            'payment_id' => Payment::factory(),
            'appointment_id' => Appointment::factory(),
            'patient_id' => Patient::factory(),
            'invoice_number' => 'INV-' . now()->format('Ym') . '-' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'total_amount' => fake()->randomFloat(2, 500, 3000),
            'issued_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => $status,
            'email_sent' => $emailSent,
            'emailed_at' => $emailSent ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }
}

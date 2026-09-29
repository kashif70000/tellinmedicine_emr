<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['paid', 'unpaid', 'failed']);
        $method = fake()->randomElement(['cash', 'card', 'online']);

        return [
            'appointment_id' => Appointment::factory(),
            'payment_intent_id' => 'pi_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'amount' => fake()->randomFloat(2, 500, 3000),
            'status' => $status,
            'method' => $method,
            'transaction_id' => 'TXN-' . strtoupper(fake()->bothify('??###??####')),
        ];
    }
}

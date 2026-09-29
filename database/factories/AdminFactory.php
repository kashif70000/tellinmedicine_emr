<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'admin']),
            'permissions' => ['manage_users', 'manage_doctors', 'manage_reports', 'manage_billing'],
        ];
    }
}

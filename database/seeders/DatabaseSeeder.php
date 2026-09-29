<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                UserSeeder::class,
                DoctorScheduleSeeder::class,
                AppointmentSeeder::class,
                PrescriptionSeeder::class,
                PaymentSeeder::class,
                InvoiceSeeder::class,
                RatingSeeder::class,
            ]);
        });
    }
}

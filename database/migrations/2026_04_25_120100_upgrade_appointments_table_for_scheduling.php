<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'date')) {
                $table->renameColumn('date', 'appointment_date');
            }

            if (Schema::hasColumn('appointments', 'time')) {
                $table->renameColumn('time', 'appointment_time');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'fee_snapshot')) {
                $table->decimal('fee_snapshot', 10, 2)->nullable()->after('status');
            }

            $table->date('appointment_date')->change();
            $table->time('appointment_time')->change();
            $table->enum('status', ['pending', 'completed', 'approved', 'cancelled'])->default('pending')->change();
        });

        if (Schema::hasColumn('appointments', 'status')) {
            DB::table('appointments')
                ->where('status', 'completed')
                ->update(['status' => 'approved']);
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending')->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(['doctor_id', 'appointment_date', 'appointment_time'], 'appointments_doctor_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_doctor_slot_unique');
        });

        if (Schema::hasColumn('appointments', 'status')) {
            DB::table('appointments')
                ->where('status', 'approved')
                ->update(['status' => 'completed']);
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'fee_snapshot')) {
                $table->dropColumn('fee_snapshot');
            }

            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending')->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'appointment_date')) {
                $table->renameColumn('appointment_date', 'date');
            }

            if (Schema::hasColumn('appointments', 'appointment_time')) {
                $table->renameColumn('appointment_time', 'time');
            }
        });
    }
};

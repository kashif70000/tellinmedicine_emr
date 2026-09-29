<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // Add appointment_id with foreign key constraint
            $table->foreignId('appointment_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('appointments')
                  ->onDelete('cascade');
            
            // Add unique constraint to prevent duplicate reviews per appointment
            $table->unique('appointment_id', 'ratings_appointment_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique('ratings_appointment_id_unique');
            $table->dropForeignKeyIfExists('ratings_appointment_id_foreign');
            $table->dropColumn('appointment_id');
        });
    }
};

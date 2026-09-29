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
        Schema::create('patient_laboratories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('laboratory_id')->constrained('laboratories')->onDelete('cascade');
            $table->boolean('is_preferred')->default(true)->index();
            $table->timestamps();

            // Composite unique constraint to prevent duplicate patient-laboratory pairs
            $table->unique(['patient_id', 'laboratory_id']);
            $table->index(['patient_id', 'is_preferred']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_laboratories');
    }
};

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
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('laboratory_id')->nullable()->constrained('laboratories')->nullOnDelete();
            $table->string('status', 30)->default('ordered');
            $table->text('clinical_notes')->nullable();
            $table->timestamp('ordered_at')->nullable();

            // Destination snapshot
            $table->string('laboratory_name_snapshot')->nullable();
            $table->string('laboratory_address_snapshot')->nullable();
            $table->string('laboratory_city_snapshot', 100)->nullable();
            $table->string('laboratory_state_snapshot', 50)->nullable();
            $table->string('laboratory_postal_code_snapshot', 20)->nullable();
            $table->string('laboratory_phone_snapshot', 50)->nullable();
            $table->timestamp('laboratory_selected_at')->nullable();
            $table->unsignedBigInteger('laboratory_selected_by')->nullable();

            // Email tracking
            $table->string('email_status', 30)->default('pending');
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();

            $table->timestamps();
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();

            // Test snapshots
            $table->string('test_name_snapshot');
            $table->string('short_name_snapshot', 50)->nullable();
            $table->string('loinc_code_snapshot', 30)->nullable();
            $table->string('category_snapshot', 100)->nullable();
            $table->string('specimen_snapshot', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['lab_order_id', 'lab_test_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
    }
};

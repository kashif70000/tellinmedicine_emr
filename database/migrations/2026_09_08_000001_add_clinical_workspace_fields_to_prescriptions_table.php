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
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->change();
            $table->string('reference_number', 50)->nullable()->unique()->after('id');
            $table->foreignId('pharmacy_id')->nullable()->after('patient_id')->constrained('pharmacies')->nullOnDelete();
            $table->string('pharmacy_name_snapshot')->nullable()->after('pharmacy_id');
            $table->string('pharmacy_address_snapshot')->nullable()->after('pharmacy_name_snapshot');
            $table->string('pharmacy_city_snapshot', 100)->nullable()->after('pharmacy_address_snapshot');
            $table->string('pharmacy_state_snapshot', 50)->nullable()->after('pharmacy_city_snapshot');
            $table->string('pharmacy_postal_code_snapshot', 20)->nullable()->after('pharmacy_state_snapshot');
            $table->string('pharmacy_phone_snapshot', 50)->nullable()->after('pharmacy_postal_code_snapshot');
            $table->timestamp('pharmacy_selected_at')->nullable()->after('pharmacy_phone_snapshot');
            $table->unsignedBigInteger('pharmacy_selected_by')->nullable()->after('pharmacy_selected_at');
            $table->string('status', 30)->default('finalized')->after('notes');
            $table->string('email_status', 30)->default('pending')->after('status');
            $table->timestamp('email_sent_at')->nullable()->after('email_status');
            $table->text('email_error')->nullable()->after('email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['pharmacy_id']);
            $table->dropColumn([
                'reference_number',
                'pharmacy_id',
                'pharmacy_name_snapshot',
                'pharmacy_address_snapshot',
                'pharmacy_city_snapshot',
                'pharmacy_state_snapshot',
                'pharmacy_postal_code_snapshot',
                'pharmacy_phone_snapshot',
                'pharmacy_selected_at',
                'pharmacy_selected_by',
                'status',
                'email_status',
                'email_sent_at',
                'email_error',
            ]);
        });
    }
};

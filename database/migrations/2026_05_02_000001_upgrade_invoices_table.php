<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add payment_id (nullable – generated before payment in some flows)
            if (! Schema::hasColumn('invoices', 'payment_id')) {
                $table->foreignId('payment_id')
                      ->nullable()
                      ->after('id')
                      ->constrained()
                      ->nullOnDelete();
            }

            // Add appointment_id for direct link
            if (! Schema::hasColumn('invoices', 'appointment_id')) {
                $table->foreignId('appointment_id')
                      ->nullable()
                      ->after('payment_id')
                      ->constrained()
                      ->nullOnDelete();
            }

            // Unique invoice number (e.g. INV-202605-00001)
            if (! Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number', 30)
                      ->nullable()
                      ->unique()
                      ->after('appointment_id');
            }

            // Make issued_date nullable (auto-populated on create)
            $table->date('issued_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['appointment_id']);
            $table->dropColumn(['payment_id', 'appointment_id', 'invoice_number']);
            $table->date('issued_date')->nullable(false)->change();
        });
    }
};

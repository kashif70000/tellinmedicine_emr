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
        Schema::table('invoices', function (Blueprint $table) {
            // Track if invoice email has been sent
            if (! Schema::hasColumn('invoices', 'email_sent')) {
                $table->boolean('email_sent')
                      ->default(false)
                      ->after('status');
            }

            // Timestamp of when invoice was emailed
            if (! Schema::hasColumn('invoices', 'emailed_at')) {
                $table->timestamp('emailed_at')
                      ->nullable()
                      ->after('email_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['email_sent', 'emailed_at']);
        });
    }
};

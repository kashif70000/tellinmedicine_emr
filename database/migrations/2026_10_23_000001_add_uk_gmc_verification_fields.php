<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('uk_verification_status')->nullable()->after('license_verified_at');
            $table->string('uk_gmc_number')->nullable()->after('uk_verification_status');
            $table->string('uk_verified_name')->nullable()->after('uk_gmc_number');
            $table->string('uk_profile_url')->nullable()->after('uk_verified_name');
            $table->string('uk_registration_status')->nullable()->after('uk_profile_url');
            $table->string('uk_licence_status')->nullable()->after('uk_registration_status');
            $table->json('uk_verification_data')->nullable()->after('uk_licence_status');
            $table->string('uk_verification_source')->nullable()->after('uk_verification_data');
            $table->text('uk_verification_message')->nullable()->after('uk_verification_source');
            $table->timestamp('uk_verified_at')->nullable()->after('uk_verification_message');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn([
                'uk_verification_status',
                'uk_gmc_number',
                'uk_verified_name',
                'uk_profile_url',
                'uk_registration_status',
                'uk_licence_status',
                'uk_verification_data',
                'uk_verification_source',
                'uk_verification_message',
                'uk_verified_at',
            ]);
        });
    }
};

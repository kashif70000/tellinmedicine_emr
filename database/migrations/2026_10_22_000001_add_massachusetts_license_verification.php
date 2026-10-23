<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('license_state', 2)->nullable()->after('license_number');
            $table->string('license_type')->nullable()->after('license_state');
            $table->date('license_issue_date')->nullable()->after('license_type');
            $table->date('license_expiration_date')->nullable()->after('license_issue_date');
            $table->string('license_verification_status')->default('pending')->after('license_expiration_date');
            $table->string('official_license_status')->nullable()->after('license_verification_status');
            $table->date('official_license_issue_date')->nullable()->after('official_license_status');
            $table->date('official_license_expiration_date')->nullable()->after('official_license_issue_date');
            $table->json('license_verification_data')->nullable()->after('official_license_expiration_date');
            $table->string('license_verification_source')->nullable()->after('license_verification_data');
            $table->text('license_verification_message')->nullable()->after('license_verification_source');
            $table->timestamp('license_verified_at')->nullable()->after('license_verification_message');
        });

        Schema::create('license_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant_name');
            $table->string('license_state', 2);
            $table->string('license_type');
            $table->string('license_number');
            $table->string('status');
            $table->text('message')->nullable();
            $table->string('source')->nullable();
            $table->json('official_data')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['license_state', 'license_number']);
            $table->index(['doctor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_verification_attempts');

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn([
                'license_state',
                'license_type',
                'license_issue_date',
                'license_expiration_date',
                'license_verification_status',
                'official_license_status',
                'official_license_issue_date',
                'official_license_expiration_date',
                'license_verification_data',
                'license_verification_source',
                'license_verification_message',
                'license_verified_at',
            ]);
        });
    }
};
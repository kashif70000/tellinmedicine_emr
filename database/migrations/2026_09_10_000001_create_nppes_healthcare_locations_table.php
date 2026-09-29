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
        Schema::create('nppes_healthcare_locations', function (Blueprint $table) {
            $table->id();
            $table->string('npi', 10)->index();
            $table->string('entity_type', 10)->nullable();
            $table->string('organization_name', 255)->nullable()->index();
            $table->string('other_organization_name', 255)->nullable();
            $table->string('taxonomy_code', 20)->nullable()->index();
            $table->string('taxonomy_description', 255)->nullable();
            $table->string('address_line_1', 191)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 255)->nullable()->index();
            $table->string('state', 50)->nullable()->index();
            $table->string('postal_code', 20)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('fax', 30)->nullable()->index();
            $table->date('enumeration_date')->nullable();
            $table->date('last_update_date')->nullable();
            $table->string('source_file', 255)->nullable();
            $table->string('source_row_hash', 64)->nullable()->index();
            $table->timestamps();

            // Composite indexes for fast candidate discovery
            $table->index(['state', 'city'], 'idx_nppes_loc_state_city');
            $table->index(['state', 'postal_code'], 'idx_nppes_loc_state_postal');
            $table->index(['organization_name', 'state'], 'idx_nppes_loc_org_state');
            $table->index(['organization_name', 'postal_code'], 'idx_nppes_loc_org_postal');

            // Unique identity index for safe idempotent upsert
            $table->unique(['npi', 'address_line_1', 'postal_code', 'taxonomy_code'], 'uniq_nppes_loc_identity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nppes_healthcare_locations');
    }
};

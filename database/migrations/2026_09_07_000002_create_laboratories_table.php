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
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('category')->nullable()->index();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->text('website')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable()->index();
            $table->string('source')->nullable()->default('google_maps');
            $table->text('external_source_url')->nullable();
            $table->timestamps();

            // Composite index for fast deduplication and lookup
            $table->index(['name', 'city', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratories');
    }
};

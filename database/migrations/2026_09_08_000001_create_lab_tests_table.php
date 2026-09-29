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
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('short_name')->nullable()->index();
            $table->string('loinc_code')->nullable()->index();
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->string('specimen')->nullable();
            $table->boolean('is_panel')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->nullable()->default(0)->index();
            $table->timestamps();

            // Composite index for fast ordering and filtering by category
            $table->index(['category', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};

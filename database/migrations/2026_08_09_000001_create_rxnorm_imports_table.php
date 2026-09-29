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
        Schema::create('rxnorm_imports', function (Blueprint $table) {
            $table->id();
            $table->string('release_version', 50)->nullable();
            $table->string('source')->default('NLM RxNorm Prescribable Content');
            $table->timestamp('imported_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rxnorm_imports');
    }
};

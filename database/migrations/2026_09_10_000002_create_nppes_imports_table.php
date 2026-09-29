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
        Schema::create('nppes_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_file', 255);
            $table->string('file_hash', 64)->nullable()->index();
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            $table->unsignedBigInteger('rows_scanned')->default(0);
            $table->unsignedBigInteger('rows_imported')->default(0);
            $table->unsignedBigInteger('pharmacy_rows')->default(0);
            $table->unsignedBigInteger('laboratory_rows')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nppes_imports');
    }
};

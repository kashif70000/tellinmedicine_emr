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
        Schema::create('rxnorm_concepts', function (Blueprint $table) {
            $table->string('rxcui', 20)->primary();
            $table->text('name');
            $table->string('tty', 10)->index();
            $table->string('suppress', 5)->default('N');
            $table->string('sab', 20)->default('RXNORM');
            $table->timestamps();
        });

        Schema::create('rxnorm_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('rxcui1', 20)->index();
            $table->string('rxcui2', 20)->index();
            $table->string('rel', 10)->nullable();
            $table->string('rela', 50)->nullable()->index();
            $table->string('sab', 20)->default('RXNORM');
            $table->timestamps();

            $table->unique(['rxcui1', 'rxcui2', 'rela'], 'rxn_rel_unique');
        });

        Schema::create('rxnorm_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('rxcui', 20)->index();
            $table->string('atn', 50)->index();
            $table->text('atv')->nullable();
            $table->string('sab', 20)->default('RXNORM');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('rxcui', 20)->unique();
            $table->string('tty', 10)->nullable()->index();
            $table->text('name');
            $table->string('generic_name')->nullable()->index();
            $table->string('brand_name')->nullable()->index();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable()->index();
            $table->string('route')->nullable()->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('rxnorm_attributes');
        Schema::dropIfExists('rxnorm_relationships');
        Schema::dropIfExists('rxnorm_concepts');
    }
};

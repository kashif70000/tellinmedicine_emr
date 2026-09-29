<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Forward-only addition of nullable fax and NPI enrichment columns.
     */
    public function up(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->string('fax', 30)->nullable()->after('phone');
            $table->string('npi', 10)->nullable()->index()->after('fax');
            $table->string('fax_source', 50)->nullable()->after('npi');
            $table->string('fax_lookup_status', 30)->nullable()->index()->after('fax_source');
            $table->unsignedTinyInteger('fax_match_score')->nullable()->after('fax_lookup_status');
            $table->text('fax_lookup_notes')->nullable()->after('fax_match_score');
            $table->timestamp('fax_verified_at')->nullable()->after('fax_lookup_notes');
            $table->timestamp('fax_last_checked_at')->nullable()->after('fax_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn([
                'fax',
                'npi',
                'fax_source',
                'fax_lookup_status',
                'fax_match_score',
                'fax_lookup_notes',
                'fax_verified_at',
                'fax_last_checked_at',
            ]);
        });
    }
};

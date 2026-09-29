<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->text('diagnosis')->nullable()->after('patient_id');
            // Make notes nullable (currently NOT NULL)
            $table->text('notes')->nullable()->change();
            // Make medicines nullable
            $table->json('medicines')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('diagnosis');
            $table->text('notes')->nullable(false)->change();
            $table->json('medicines')->nullable(false)->change();
        });
    }
};

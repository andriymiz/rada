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
        Schema::table('convocations', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('council_organizations')
                ->restrictOnDelete();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'date']);
            $table->dropConstrainedForeignId('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('council_organizations')
                ->restrictOnDelete();
            $table->index(['organization_id', 'date']);
        });

        Schema::table('convocations', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->dropConstrainedForeignId('organization_id');
            $table->unique('name');
        });
    }
};

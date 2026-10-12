<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the convocation name column to label.
     */
    public function up(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->renameColumn('name', 'label');
            $table->unique(['organization_id', 'label']);
        });
    }

    /**
     * Restore the original convocation name column.
     */
    public function down(): void
    {
        Schema::table('convocations', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'label']);
            $table->renameColumn('label', 'name');
            $table->unique(['organization_id', 'name']);
        });
    }
};

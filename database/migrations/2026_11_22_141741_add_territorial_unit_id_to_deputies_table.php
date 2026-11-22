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
        Schema::table('deputies', function (Blueprint $table): void {
            $table->unsignedInteger('territorial_unit_id')->nullable();
            $table->foreign('territorial_unit_id')
                ->references('system_number')
                ->on('territorial_units')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table): void {
            $table->dropForeign(['territorial_unit_id']);
            $table->dropColumn('territorial_unit_id');
        });
    }
};

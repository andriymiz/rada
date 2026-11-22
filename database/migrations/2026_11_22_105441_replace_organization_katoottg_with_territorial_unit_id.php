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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedInteger('territorial_unit_id')->nullable();
            $table->foreign('territorial_unit_id')
                ->references('system_number')
                ->on('territorial_units')
                ->nullOnDelete();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('katoottg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('katoottg', 19)->nullable();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropForeign(['territorial_unit_id']);
            $table->dropColumn('territorial_unit_id');
        });
    }
};

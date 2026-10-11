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
        Schema::table('council_sessions', function (Blueprint $table) {
            $table->dropForeign(['convocation_id']);
        });

        Schema::table('parliamentary_convocations', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::rename('parliamentary_convocations', 'convocations');

        Schema::table('convocations', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('council_sessions', function (Blueprint $table) {
            $table->foreign('convocation_id')
                ->references('id')
                ->on('convocations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('council_sessions', function (Blueprint $table) {
            $table->dropForeign(['convocation_id']);
        });

        Schema::table('convocations', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::rename('convocations', 'parliamentary_convocations');

        Schema::table('parliamentary_convocations', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('council_sessions', function (Blueprint $table) {
            $table->foreign('convocation_id')
                ->references('id')
                ->on('parliamentary_convocations')
                ->restrictOnDelete();
        });
    }
};

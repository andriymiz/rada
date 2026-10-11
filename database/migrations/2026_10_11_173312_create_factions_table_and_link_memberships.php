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
        Schema::create('factions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['convocation_id', 'name']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('faction_id')
                ->nullable()
                ->constrained('factions')
                ->restrictOnDelete();
            $table->dropColumn('faction_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faction_id');
            $table->string('faction_name')->nullable();
        });

        Schema::dropIfExists('factions');
    }
};

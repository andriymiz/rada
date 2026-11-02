<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('political_parties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('edrpou', 8)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('permanent_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['convocation_id', 'name']);
        });

        Schema::create('factions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['convocation_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factions');
        Schema::dropIfExists('permanent_commissions');
        Schema::dropIfExists('political_parties');
    }
};

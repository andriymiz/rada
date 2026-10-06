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
        Schema::create('parliamentary_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('parliamentary_convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->unique(['convocation_id', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parliamentary_sessions');
    }
};

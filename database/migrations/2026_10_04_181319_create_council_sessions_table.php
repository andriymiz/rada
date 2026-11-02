<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->unique(['convocation_id', 'name']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_sessions');
    }
};

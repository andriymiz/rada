<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('council_organizations')
                ->restrictOnDelete();
            $table->string('label');
            $table->date('date_from')->nullable();
            $table->date('date_until')->nullable();
            $table->unique(['organization_id', 'label']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocations');
    }
};

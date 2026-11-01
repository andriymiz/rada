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
        Schema::create('officials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('council_organizations')->restrictOnDelete();
            $table->string('position');
            $table->text('image')->nullable();
            $table->text('homepage')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officials');
    }
};

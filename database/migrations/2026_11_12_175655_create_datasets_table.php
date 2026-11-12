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
        Schema::create('datasets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('accrual_periodicity');
            $table->json('keywords');
            $table->text('purpose');
            $table->json('distribution_formats');
            $table->foreignId('publisher_id')->constrained('council_organizations')->restrictOnDelete();
            $table->foreignId('responsible_official_id')->constrained('officials')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};

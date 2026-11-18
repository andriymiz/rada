<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('head_id')->nullable()->constrained('officials')->nullOnDelete();
            $table->foreignId('parent_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->text('description');
            $table->text('constituent_doc')->nullable();
            $table->text('homepage')->nullable();
            $table->string('contact_point');
            $table->json('emails');
            $table->json('phones');
            $table->text('opening_hours')->nullable();
            $table->text('availability_restriction')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};

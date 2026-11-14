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
        Schema::create('dataset_metas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dataset_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('accrual_periodicity');
            $table->json('keywords');
            $table->text('purpose');
            $table->string('contact_point_name');
            $table->string('contact_point_email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_metas');
    }
};

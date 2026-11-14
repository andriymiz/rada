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
        Schema::table('datasets', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable()->change();
            $table->dropForeign(['responsible_official_id']);
            $table->dropColumn([
                'description',
                'accrual_periodicity',
                'keywords',
                'purpose',
                'distribution_formats',
                'responsible_official_id',
            ]);
        });

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
        Schema::table('datasets', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->string('accrual_periodicity')->nullable();
            $table->json('keywords')->nullable();
            $table->text('purpose')->nullable();
            $table->json('distribution_formats')->nullable();
            $table->foreignId('responsible_official_id')
                ->nullable()
                ->constrained('officials')
                ->restrictOnDelete();
        });

        Schema::dropIfExists('dataset_metas');

        Schema::table('datasets', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable(false)->change();
        });
    }
};

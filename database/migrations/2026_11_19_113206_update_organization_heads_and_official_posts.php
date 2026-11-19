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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->foreignId('head_id')
                ->nullable()
                ->constrained('officials')
                ->nullOnDelete();
        });

        Schema::table('officials', function (Blueprint $table): void {
            $table->renameColumn('is_head', 'is_post');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_id');
        });

        Schema::table('officials', function (Blueprint $table): void {
            $table->renameColumn('is_post', 'is_head');
        });
    }
};

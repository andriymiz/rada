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
            $table->string('status')->default('unknown');
            $table->text('status_note')->nullable();
            $table->string('info_owner')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table): void {
            $table->dropColumn(['status', 'status_note', 'info_owner']);
        });
    }
};

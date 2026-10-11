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
        Schema::table('motions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roll_call_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motions', function (Blueprint $table) {
            $table->foreignId('roll_call_import_id')
                ->nullable()
                ->constrained('roll_call_imports')
                ->nullOnDelete();
        });
    }
};

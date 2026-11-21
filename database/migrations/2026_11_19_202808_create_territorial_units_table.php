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
        Schema::create('territorial_units', function (Blueprint $table) {
            $table->unsignedInteger('system_number')->primary();
            $table->unsignedInteger('parent_number')->nullable()->index();
            $table->char('katottg_code', 19)->unique();
            $table->enum('category', ['O', 'K', 'P', 'H', 'M', 'T', 'C', 'X', 'B']);
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territorial_units');
    }
};

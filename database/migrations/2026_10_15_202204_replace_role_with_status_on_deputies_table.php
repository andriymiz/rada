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
        Schema::table('deputies', function (Blueprint $table): void {
            $table->unsignedTinyInteger('status')->default(1);
            $table->dropColumn('role');
        });
    }

    /**
     * Restore the previous schema with a generic role value; discarded role text cannot be derived from status.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table): void {
            $table->string('role')->default('Депутат місцевої ради');
            $table->dropColumn('status');
        });
    }
};

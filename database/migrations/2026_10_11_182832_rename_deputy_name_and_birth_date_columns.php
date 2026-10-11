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
        Schema::table('deputies', function (Blueprint $table) {
            $table->renameColumn('family_name', 'last_name');
            $table->renameColumn('additional_name', 'middle_name');
            $table->renameColumn('birth_date', 'date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table) {
            $table->renameColumn('last_name', 'family_name');
            $table->renameColumn('middle_name', 'additional_name');
            $table->renameColumn('date_of_birth', 'birth_date');
        });
    }
};

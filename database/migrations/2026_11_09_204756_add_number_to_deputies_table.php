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
            $table->unsignedInteger('number')->nullable()->after('convocation_id');
            $table->unique(
                ['convocation_id', 'number'],
                'deputies_convocation_number_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table) {
            $table->dropUnique('deputies_convocation_number_unique');
            $table->dropColumn('number');
        });
    }
};

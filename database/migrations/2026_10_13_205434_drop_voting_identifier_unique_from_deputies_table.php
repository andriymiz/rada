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
            $table->dropUnique('deputies_convocation_voting_identifier_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table): void {
            $table->unique(
                ['convocation_id', 'voting_identifier'],
                'deputies_convocation_voting_identifier_unique',
            );
        });
    }
};

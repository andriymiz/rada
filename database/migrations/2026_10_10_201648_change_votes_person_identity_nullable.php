<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->change();
            $table->string('voter_identifier')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('votes')->whereNull('person_id')->orWhereNull('voter_identifier')->exists()) {
            throw new RuntimeException('Cannot make imported voter identities required while unresolved imported votes exist.');
        }

        Schema::table('votes', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable(false)->change();
            $table->string('voter_identifier')->nullable(false)->change();
        });
    }
};

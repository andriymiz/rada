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
        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->foreignId('plenary_meeting_id')
                ->nullable()
                ->constrained('plenary_meetings')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plenary_meeting_id');
        });
    }
};

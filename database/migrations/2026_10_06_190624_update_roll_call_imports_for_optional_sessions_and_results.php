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
            $table->dropForeign(['session_id']);
            $table->foreignId('session_id')
                ->nullable()
                ->change();
            $table->foreign('session_id')
                ->references('id')
                ->on('parliamentary_sessions')
                ->restrictOnDelete();
            $table->json('parsed_result')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn(['parsed_result', 'deleted_at']);
            $table->foreignId('session_id')
                ->nullable(false)
                ->change();
            $table->foreign('session_id')
                ->references('id')
                ->on('parliamentary_sessions')
                ->restrictOnDelete();
        });
    }
};

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
        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->dropForeign(['parliamentary_session_id']);
            $table->dropIndex(['parliamentary_session_id', 'date']);
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
        });

        Schema::rename('parliamentary_sessions', 'council_sessions');

        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->renameColumn('parliamentary_session_id', 'council_session_id');
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->renameColumn('session_id', 'council_session_id');
        });

        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->foreign('council_session_id')
                ->references('id')
                ->on('council_sessions')
                ->restrictOnDelete();
            $table->index(['council_session_id', 'date']);
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->foreign('council_session_id')
                ->references('id')
                ->on('council_sessions')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->dropForeign(['council_session_id']);
            $table->dropIndex(['council_session_id', 'date']);
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->dropForeign(['council_session_id']);
        });

        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->renameColumn('council_session_id', 'parliamentary_session_id');
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->renameColumn('council_session_id', 'session_id');
        });

        Schema::rename('council_sessions', 'parliamentary_sessions');

        Schema::table('plenary_meetings', function (Blueprint $table) {
            $table->foreign('parliamentary_session_id')
                ->references('id')
                ->on('parliamentary_sessions')
                ->restrictOnDelete();
            $table->index(['parliamentary_session_id', 'date']);
        });

        Schema::table('roll_call_imports', function (Blueprint $table) {
            $table->foreign('session_id')
                ->references('id')
                ->on('parliamentary_sessions')
                ->restrictOnDelete();
        });
    }
};

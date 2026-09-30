<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            $table->string('session_number', 64)->nullable()->after('session_id');
        });

        Schema::table('staged_vote_records', function (Blueprint $table): void {
            $table->string('question_number', 64)->nullable()->after('question_id');
            $table->text('question_title')->nullable()->after('question_number');
            $table->string('deputy_name')->nullable()->after('deputy_id');
            $table->text('validation_error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('staged_vote_records', function (Blueprint $table): void {
            $table->dropColumn(['question_number', 'question_title', 'deputy_name', 'validation_error']);
        });

        Schema::table('imports', function (Blueprint $table): void {
            $table->dropColumn('session_number');
        });
    }
};

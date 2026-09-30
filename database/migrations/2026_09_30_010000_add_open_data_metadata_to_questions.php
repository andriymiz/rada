<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staged_vote_records', function (Blueprint $table): void {
            $table->string('voting_result', 32)->nullable()->after('question_title');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->string('project_number', 64)->nullable();
            $table->string('voting_result', 32)->nullable();
            $table->string('decision_document_url')->nullable();
            $table->string('decision_document_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['project_number', 'voting_result', 'decision_document_url', 'decision_document_name']);
        });

        Schema::table('staged_vote_records', function (Blueprint $table): void {
            $table->dropColumn('voting_result');
        });
    }
};

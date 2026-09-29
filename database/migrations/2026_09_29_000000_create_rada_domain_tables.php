<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('council_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('session_number')->nullable()->index();
            $table->date('held_at')->nullable()->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->timestamps();
        });

        Schema::create('deputies', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('source_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->unique();
            $table->string('status', 24)->default('uploaded')->index();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('council_sessions')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('council_sessions')->cascadeOnDelete();
            $table->string('question_number', 64);
            $table->text('title');
            $table->timestamps();
            $table->unique(['session_id', 'question_number']);
        });

        Schema::create('roll_call_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deputy_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('result', 24)->index();
            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['question_id', 'deputy_id']);
        });

        Schema::create('staged_vote_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deputy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('raw_result', 255)->nullable();
            $table->string('recognized_result', 24)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['import_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 128)->index();
            $table->nullableMorphs('auditable');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('staged_vote_records');
        Schema::dropIfExists('roll_call_votes');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('source_documents');
        Schema::dropIfExists('deputies');
        Schema::dropIfExists('council_sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');
    }
};

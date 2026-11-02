<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_call_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plenary_meeting_id')
                ->nullable()
                ->constrained('plenary_meetings')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->json('parsed_result')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_call_imports');
    }
};

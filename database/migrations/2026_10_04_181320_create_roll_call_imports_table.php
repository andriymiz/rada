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
        Schema::create('roll_call_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('parliamentary_sessions')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('status')->default('queued');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roll_call_imports');
    }
};

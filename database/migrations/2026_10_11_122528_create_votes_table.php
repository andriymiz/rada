<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('deputy_id')->nullable()->constrained('deputies')->restrictOnDelete();
            $table->string('voter_identifier')->nullable();
            $table->string('voter_name');
            $table->string('option');
            $table->timestamps();

            $table->unique(['vote_event_id', 'deputy_id']);
            $table->index('voter_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestamps();
        });

        Schema::create('plenary_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_session_id')
                ->constrained('council_sessions')
                ->restrictOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->index(['council_session_id', 'date']);
        });

        Schema::create('motions', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('plenary_meeting_id')
                ->constrained('plenary_meetings')
                ->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->text('title');
            $table->string('project_number')->nullable();
            $table->string('result');
            $table->text('text_url')->nullable();
            $table->string('text');
            $table->timestamps();

            $table->unique(['plenary_meeting_id', 'number']);
        });

        Schema::create('vote_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motion_id')
                ->constrained('motions')
                ->restrictOnDelete();
            $table->string('identifier')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_events');
        Schema::dropIfExists('motions');
        Schema::dropIfExists('plenary_meetings');
        Schema::dropIfExists('people');
    }
};

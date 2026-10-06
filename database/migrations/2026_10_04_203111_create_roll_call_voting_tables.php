<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('edrpou', 8)->unique();
            $table->string('katoottg', 19)->unique();
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('voting_identifier')->unique();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')
                ->constrained('council_organizations')
                ->restrictOnDelete();
            $table->string('role');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'end_date']);
        });

        Schema::create('plenary_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('council_organizations')
                ->restrictOnDelete();
            $table->foreignId('parliamentary_session_id')
                ->constrained('parliamentary_sessions')
                ->restrictOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->index(['organization_id', 'date']);
            $table->index(['parliamentary_session_id', 'date']);
        });

        Schema::create('motions', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('plenary_meeting_id')
                ->constrained('plenary_meetings')
                ->restrictOnDelete();
            $table->foreignId('roll_call_import_id')
                ->nullable()
                ->constrained('roll_call_imports')
                ->nullOnDelete();
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
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->string('voter_identifier');
            $table->string('voter_name');
            $table->string('option');
            $table->timestamps();

            $table->unique(['vote_event_id', 'person_id']);
            $table->index('voter_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('vote_events');
        Schema::dropIfExists('motions');
        Schema::dropIfExists('plenary_meetings');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('people');
        Schema::dropIfExists('council_organizations');
    }
};

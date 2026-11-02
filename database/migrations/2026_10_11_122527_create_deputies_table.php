<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deputies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('convocation_id')
                ->nullable()
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->foreignId('political_party_id')
                ->nullable()
                ->constrained('political_parties')
                ->restrictOnDelete();
            $table->foreignId('permanent_commission_id')
                ->nullable()
                ->constrained('permanent_commissions')
                ->restrictOnDelete();
            $table->foreignId('faction_id')
                ->nullable()
                ->constrained('factions')
                ->restrictOnDelete();
            $table->string('voting_identifier')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('faction_position')->nullable();
            $table->string('commission_position')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->text('homepage')->nullable();
            $table->text('image')->nullable();
            $table->text('biography')->nullable();
            $table->timestamps();

            $table->unique(
                ['person_id', 'convocation_id'],
                'deputies_person_convocation_unique',
            );
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('deputies');
    }
};

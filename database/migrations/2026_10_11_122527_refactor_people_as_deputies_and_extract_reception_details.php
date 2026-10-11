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
        Schema::create('deputies', function (Blueprint $table): void {
            $table->id();
            $table->string('family_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('additional_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('image')->nullable();
            $table->text('biography')->nullable();
            $table->text('email')->nullable();
            $table->text('telephone')->nullable();
            $table->text('homepage')->nullable();
            $table->text('account')->nullable();
            $table->timestamps();
        });

        Schema::create('deputy_receptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->unique()->constrained('deputies')->restrictOnDelete();
            $table->string('katoottg', 19)->nullable();
            $table->string('address_post_code', 12)->nullable();
            $table->string('address_admin_unit_l1')->nullable();
            $table->string('address_admin_unit_l2')->nullable();
            $table->string('address_admin_unit_l3')->nullable();
            $table->string('address_admin_unit_l4')->nullable();
            $table->string('address_post_name')->nullable();
            $table->string('address_thoroughfare')->nullable();
            $table->string('address_locator_designator')->nullable();
            $table->string('address_locator_building')->nullable();
            $table->string('address_locator_name')->nullable();
            $table->text('opening_hours')->nullable();
            $table->text('availability_restriction')->nullable();
            $table->timestamps();
        });

        Schema::create('memberships_new', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->constrained('deputies')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('council_organizations')->restrictOnDelete();
            $table->foreignId('convocation_id')->nullable()->constrained('convocations')->restrictOnDelete();
            $table->foreignId('political_party_id')->nullable()->constrained('political_parties')->restrictOnDelete();
            $table->foreignId('permanent_commission_id')->nullable()->constrained('permanent_commissions')->restrictOnDelete();
            $table->string('voting_identifier')->nullable();
            $table->string('role');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('faction_name')->nullable();
            $table->string('faction_position')->nullable();
            $table->string('commission_position')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'end_date']);
            $table->unique(
                ['deputy_id', 'organization_id', 'convocation_id'],
                'memberships_deputy_org_convocation_unique',
            );
            $table->unique(
                ['convocation_id', 'voting_identifier'],
                'memberships_convocation_voting_identifier_unique',
            );
        });

        Schema::create('votes_new', function (Blueprint $table): void {
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

        Schema::drop('votes');
        Schema::drop('memberships');
        Schema::drop('people');
        Schema::rename('memberships_new', 'memberships');
        Schema::rename('votes_new', 'votes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('This deputy model migration is intentionally irreversible because deputy identifiers are now database-generated and reception details are no longer membership-scoped.');
    }
};

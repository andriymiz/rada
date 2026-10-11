<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('identifier')->nullable()->after('id');
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
            $table->string('voting_identifier')->nullable()->change();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->string('identifier')->nullable(false)->change();
            $table->unique('identifier');
        });

        Schema::create('political_parties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('edrpou', 8)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('permanent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convocation_id')
                ->constrained('convocations')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['convocation_id', 'name']);
        });

        Schema::table('memberships', function (Blueprint $table) {
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
            $table->string('faction_name')->nullable();
            $table->string('faction_position')->nullable();
            $table->string('commission_position')->nullable();
            $table->unsignedInteger('area_id')->nullable();
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

            $table->unique(
                ['person_id', 'organization_id', 'convocation_id'],
                'memberships_person_org_convocation_unique',
            );
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('people')->whereNull('voting_identifier')->exists()) {
            throw new RuntimeException('Cannot make voting identifiers required while deputies without them exist.');
        }

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropUnique('memberships_person_org_convocation_unique');
            $table->dropConstrainedForeignId('permanent_commission_id');
            $table->dropConstrainedForeignId('political_party_id');
            $table->dropConstrainedForeignId('convocation_id');
            $table->dropColumn([
                'faction_name',
                'faction_position',
                'commission_position',
                'area_id',
                'katoottg',
                'address_post_code',
                'address_admin_unit_l1',
                'address_admin_unit_l2',
                'address_admin_unit_l3',
                'address_admin_unit_l4',
                'address_post_name',
                'address_thoroughfare',
                'address_locator_designator',
                'address_locator_building',
                'address_locator_name',
                'opening_hours',
                'availability_restriction',
            ]);
        });

        Schema::dropIfExists('permanent_commissions');
        Schema::dropIfExists('political_parties');

        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['identifier']);
            $table->dropColumn([
                'identifier',
                'family_name',
                'first_name',
                'additional_name',
                'gender',
                'birth_date',
                'image',
                'biography',
                'email',
                'telephone',
                'homepage',
                'account',
            ]);
            $table->string('voting_identifier')->nullable(false)->change();
        });
    }
};

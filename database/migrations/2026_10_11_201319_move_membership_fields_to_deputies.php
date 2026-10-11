<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('memberships')->whereNull('convocation_id')->exists()) {
            throw new RuntimeException('Cannot move memberships without a convocation because organization_id is not retained.');
        }

        $duplicateConvocationMemberships = DB::table('memberships')
            ->select(['deputy_id', 'convocation_id'])
            ->groupBy(['deputy_id', 'convocation_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateConvocationMemberships) {
            throw new RuntimeException('Cannot create one deputy record per convocation while duplicate memberships exist.');
        }

        Schema::table('deputies', function (Blueprint $table): void {
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
            $table->string('role')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('faction_position')->nullable();
            $table->string('commission_position')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->text('homepage')->nullable();
            $table->dropUnique('deputies_person_id_unique');
        });

        DB::table('deputies')
            ->select(['id', 'person_id', 'biography'])
            ->orderBy('id')
            ->chunkById(200, function ($deputies): void {
                foreach ($deputies as $deputy) {
                    $homepage = DB::table('people')
                        ->where('id', $deputy->person_id)
                        ->value('homepage');
                    $memberships = DB::table('memberships')
                        ->where('deputy_id', $deputy->id)
                        ->orderBy('id')
                        ->get();

                    DB::table('deputies')
                        ->where('id', $deputy->id)
                        ->update(['homepage' => $homepage]);

                    foreach ($memberships as $index => $membership) {
                        $termAttributes = $this->termAttributes($membership);

                        if ($index === 0) {
                            DB::table('deputies')
                                ->where('id', $deputy->id)
                                ->update($termAttributes);

                            continue;
                        }

                        DB::table('deputies')->insert([
                            'person_id' => $deputy->person_id,
                            'biography' => $deputy->biography,
                            'homepage' => $homepage,
                            'created_at' => $membership->created_at,
                            'updated_at' => $membership->updated_at,
                            ...$termAttributes,
                        ]);
                    }
                }
            });

        $lastVoteId = 0;

        do {
            $votes = DB::table('votes')
                ->join('vote_events', 'vote_events.id', '=', 'votes.vote_event_id')
                ->join('motions', 'motions.id', '=', 'vote_events.motion_id')
                ->join('plenary_meetings', 'plenary_meetings.id', '=', 'motions.plenary_meeting_id')
                ->join('council_sessions', 'council_sessions.id', '=', 'plenary_meetings.council_session_id')
                ->whereNotNull('votes.deputy_id')
                ->where('votes.id', '>', $lastVoteId)
                ->orderBy('votes.id')
                ->limit(200)
                ->get([
                    'votes.id as vote_id',
                    'votes.deputy_id',
                    'council_sessions.convocation_id',
                ]);

            foreach ($votes as $vote) {
                $lastVoteId = $vote->vote_id;

                if ($vote->convocation_id === null) {
                    continue;
                }

                $personId = DB::table('deputies')
                    ->where('id', $vote->deputy_id)
                    ->value('person_id');

                $termDeputyId = DB::table('deputies')
                    ->where('person_id', $personId)
                    ->where('convocation_id', $vote->convocation_id)
                    ->value('id');

                if ($termDeputyId !== null && $termDeputyId !== $vote->deputy_id) {
                    DB::table('votes')
                        ->where('id', $vote->vote_id)
                        ->update(['deputy_id' => $termDeputyId]);
                }
            }
        } while ($votes->isNotEmpty());

        Schema::drop('memberships');

        Schema::table('deputies', function (Blueprint $table): void {
            $table->unique(
                ['person_id', 'convocation_id'],
                'deputies_person_convocation_unique',
            );
            $table->unique(
                ['convocation_id', 'voting_identifier'],
                'deputies_convocation_voting_identifier_unique',
            );
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn('homepage');
        });
    }

    public function down(): void
    {
        $missingOrganization = DB::table('deputies')
            ->join('convocations', 'convocations.id', '=', 'deputies.convocation_id')
            ->whereNotNull('deputies.convocation_id')
            ->whereNull('convocations.organization_id')
            ->exists();

        if ($missingOrganization) {
            throw new RuntimeException('Cannot restore memberships when a convocation has no council organization.');
        }

        $duplicateVotes = DB::table('votes')
            ->join('deputies', 'deputies.id', '=', 'votes.deputy_id')
            ->select(['votes.vote_event_id', 'deputies.person_id'])
            ->groupBy(['votes.vote_event_id', 'deputies.person_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateVotes) {
            throw new RuntimeException('Cannot merge term deputies because one vote event contains multiple votes for the same person.');
        }

        $deputies = DB::table('deputies')
            ->orderBy('person_id')
            ->orderBy('id')
            ->get(['id', 'person_id', 'biography', 'homepage'])
            ->groupBy('person_id');
        $canonicalDeputyIds = [];

        foreach ($deputies as $personId => $personDeputies) {
            $canonicalDeputy = $personDeputies->first();

            foreach ($personDeputies->skip(1) as $deputy) {
                if (
                    $deputy->biography !== $canonicalDeputy->biography
                    || $deputy->homepage !== $canonicalDeputy->homepage
                ) {
                    throw new RuntimeException("Cannot merge term deputies with different profile data for person {$personId}.");
                }
            }

            $canonicalDeputyIds[$personId] = $canonicalDeputy->id;
        }

        $multipleReceptions = DB::table('deputy_receptions')
            ->join('deputies', 'deputies.id', '=', 'deputy_receptions.deputy_id')
            ->select('deputies.person_id')
            ->groupBy('deputies.person_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($multipleReceptions) {
            throw new RuntimeException('Cannot merge term deputies that each have separate reception records.');
        }

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->constrained('deputies')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('council_organizations')->restrictOnDelete();
            $table->foreignId('convocation_id')->nullable()->constrained('convocations')->restrictOnDelete();
            $table->foreignId('political_party_id')->nullable()->constrained('political_parties')->restrictOnDelete();
            $table->foreignId('permanent_commission_id')->nullable()->constrained('permanent_commissions')->restrictOnDelete();
            $table->foreignId('faction_id')->nullable()->constrained('factions')->restrictOnDelete();
            $table->string('voting_identifier')->nullable();
            $table->string('role');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
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

        DB::table('deputies')
            ->join('convocations', 'convocations.id', '=', 'deputies.convocation_id')
            ->whereNotNull('deputies.convocation_id')
            ->orderBy('deputies.id')
            ->select([
                'deputies.id',
                'deputies.person_id',
                'deputies.convocation_id',
                'deputies.political_party_id',
                'deputies.permanent_commission_id',
                'deputies.faction_id',
                'deputies.voting_identifier',
                'deputies.role',
                'deputies.start_date',
                'deputies.end_date',
                'deputies.faction_position',
                'deputies.commission_position',
                'deputies.area_id',
                'deputies.created_at',
                'deputies.updated_at',
                'convocations.organization_id',
            ])
            ->chunkById(200, function ($termDeputies) use ($canonicalDeputyIds): void {
                foreach ($termDeputies as $deputy) {
                    DB::table('memberships')->insert([
                        'deputy_id' => $canonicalDeputyIds[$deputy->person_id],
                        'organization_id' => $deputy->organization_id,
                        'convocation_id' => $deputy->convocation_id,
                        'political_party_id' => $deputy->political_party_id,
                        'permanent_commission_id' => $deputy->permanent_commission_id,
                        'faction_id' => $deputy->faction_id,
                        'voting_identifier' => $deputy->voting_identifier,
                        'role' => $deputy->role,
                        'start_date' => $deputy->start_date,
                        'end_date' => $deputy->end_date,
                        'faction_position' => $deputy->faction_position,
                        'commission_position' => $deputy->commission_position,
                        'area_id' => $deputy->area_id,
                        'created_at' => $deputy->created_at,
                        'updated_at' => $deputy->updated_at,
                    ]);
                }
            }, 'deputies.id', 'id');

        Schema::table('people', function (Blueprint $table): void {
            $table->text('homepage')->nullable();
        });

        foreach ($canonicalDeputyIds as $personId => $canonicalDeputyId) {
            $canonical = DB::table('deputies')->where('id', $canonicalDeputyId)->firstOrFail();

            DB::table('people')
                ->where('id', $personId)
                ->update(['homepage' => $canonical->homepage]);

            $termDeputyIds = DB::table('deputies')
                ->where('person_id', $personId)
                ->where('id', '!=', $canonicalDeputyId)
                ->pluck('id');

            foreach ($termDeputyIds as $termDeputyId) {
                DB::table('votes')
                    ->where('deputy_id', $termDeputyId)
                    ->update(['deputy_id' => $canonicalDeputyId]);

                DB::table('contact_points')
                    ->where('contactable_type', 'App\Models\Deputy')
                    ->where('contactable_id', $termDeputyId)
                    ->update(['contactable_id' => $canonicalDeputyId]);

                DB::table('deputy_receptions')
                    ->where('deputy_id', $termDeputyId)
                    ->update(['deputy_id' => $canonicalDeputyId]);

                DB::table('deputies')
                    ->where('id', $termDeputyId)
                    ->delete();
            }
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('deputies', function (Blueprint $table): void {
                $table->dropUnique('deputies_person_convocation_unique');
                $table->dropUnique('deputies_convocation_voting_identifier_unique');
                $table->dropConstrainedForeignId('convocation_id');
                $table->dropConstrainedForeignId('political_party_id');
                $table->dropConstrainedForeignId('permanent_commission_id');
                $table->dropConstrainedForeignId('faction_id');
                $table->dropColumn([
                    'voting_identifier',
                    'role',
                    'start_date',
                    'end_date',
                    'faction_position',
                    'commission_position',
                    'area_id',
                    'homepage',
                ]);
                $table->unique('person_id');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function termAttributes(stdClass $membership): array
    {
        return [
            'convocation_id' => $membership->convocation_id,
            'political_party_id' => $membership->political_party_id,
            'permanent_commission_id' => $membership->permanent_commission_id,
            'faction_id' => $membership->faction_id,
            'voting_identifier' => $membership->voting_identifier,
            'role' => $membership->role,
            'start_date' => $membership->start_date,
            'end_date' => $membership->end_date,
            'faction_position' => $membership->faction_position,
            'commission_position' => $membership->commission_position,
            'area_id' => $membership->area_id,
        ];
    }
};

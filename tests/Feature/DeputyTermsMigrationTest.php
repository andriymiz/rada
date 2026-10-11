<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('moves membership data to one deputy per convocation and keeps votes with their term', function () {
    $connectionName = 'deputy_terms_migration_test';
    $defaultConnection = config('database.default');

    config([
        "database.connections.{$connectionName}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => $connectionName,
    ]);
    DB::purge($connectionName);

    try {
        $schema = Schema::connection($connectionName);
        $schema->create('council_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $schema->create('convocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('council_organizations')->restrictOnDelete();
            $table->string('name');
        });
        $schema->create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('image')->nullable();
            $table->text('homepage')->nullable();
            $table->timestamps();
        });
        $schema->create('political_parties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $schema->create('permanent_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')->constrained()->restrictOnDelete();
            $table->string('name');
        });
        $schema->create('factions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')->constrained()->restrictOnDelete();
            $table->string('name');
        });
        $schema->create('deputies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->text('biography')->nullable();
            $table->timestamps();
        });
        $schema->create('council_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocation_id')->constrained()->restrictOnDelete();
        });
        $schema->create('plenary_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('council_session_id')->constrained()->restrictOnDelete();
        });
        $schema->create('motions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plenary_meeting_id')->constrained()->restrictOnDelete();
        });
        $schema->create('vote_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motion_id')->constrained()->restrictOnDelete();
        });
        $schema->create('votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('deputy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('voter_identifier')->nullable();
            $table->string('voter_name');
            $table->string('option');
            $table->timestamps();
        });
        $schema->create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('council_organizations')->restrictOnDelete();
            $table->foreignId('convocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('political_party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('permanent_commission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('faction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('voting_identifier')->nullable();
            $table->string('role');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('faction_position')->nullable();
            $table->string('commission_position')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->timestamps();
        });
        $schema->create('deputy_receptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deputy_id')->unique()->constrained()->restrictOnDelete();
            $table->string('katoottg')->nullable();
        });
        $schema->create('contact_points', function (Blueprint $table): void {
            $table->id();
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');
            $table->string('type');
            $table->text('value');
        });

        $timestamp = now()->toDateTimeString();
        $organizationId = DB::table('council_organizations')->insertGetId(['name' => 'Рада']);
        $firstConvocationId = DB::table('convocations')->insertGetId([
            'organization_id' => $organizationId,
            'name' => 'Восьме скликання',
        ]);
        $secondConvocationId = DB::table('convocations')->insertGetId([
            'organization_id' => $organizationId,
            'name' => 'Дев’яте скликання',
        ]);
        $personId = DB::table('people')->insertGetId([
            'last_name' => 'Іваненко',
            'first_name' => 'Іван',
            'middle_name' => 'Іванович',
            'gender' => 1,
            'date_of_birth' => '1980-01-01',
            'homepage' => 'https://council.example.test/ivan',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $deputyId = DB::table('deputies')->insertGetId([
            'person_id' => $personId,
            'biography' => 'Біографія депутата.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $firstPartyId = DB::table('political_parties')->insertGetId(['name' => 'Партія перша']);
        $secondPartyId = DB::table('political_parties')->insertGetId(['name' => 'Партія друга']);

        foreach ([
            [$firstConvocationId, $firstPartyId, 'TERM-001', '2020-11-01'],
            [$secondConvocationId, $secondPartyId, 'TERM-002', '2025-11-01'],
        ] as [$convocationId, $partyId, $identifier, $startDate]) {
            DB::table('memberships')->insert([
                'deputy_id' => $deputyId,
                'organization_id' => $organizationId,
                'convocation_id' => $convocationId,
                'political_party_id' => $partyId,
                'voting_identifier' => $identifier,
                'role' => 'Депутат міської ради',
                'start_date' => $startDate,
                'faction_position' => null,
                'commission_position' => null,
                'area_id' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $voteEventIds = [];

        foreach ([$firstConvocationId, $secondConvocationId] as $convocationId) {
            $sessionId = DB::table('council_sessions')->insertGetId(['convocation_id' => $convocationId]);
            $meetingId = DB::table('plenary_meetings')->insertGetId(['council_session_id' => $sessionId]);
            $motionId = DB::table('motions')->insertGetId(['plenary_meeting_id' => $meetingId]);
            $voteEventIds[] = DB::table('vote_events')->insertGetId(['motion_id' => $motionId]);
        }

        $voteIds = [];

        foreach ($voteEventIds as $index => $voteEventId) {
            $voteIds[] = DB::table('votes')->insertGetId([
                'vote_event_id' => $voteEventId,
                'deputy_id' => $deputyId,
                'voter_identifier' => 'SNAPSHOT-'.$index,
                'voter_name' => 'Іваненко Іван Іванович',
                'option' => 'За',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        DB::table('deputy_receptions')->insert([
            'deputy_id' => $deputyId,
            'katoottg' => 'UA12345678901234567',
        ]);
        DB::table('contact_points')->insert([
            'contactable_type' => 'App\Models\Deputy',
            'contactable_id' => $deputyId,
            'type' => 'email',
            'value' => 'deputy@example.test',
        ]);

        $migration = require database_path('migrations/2026_10_11_201319_move_membership_fields_to_deputies.php');
        $migration->up();

        $termDeputies = DB::table('deputies')
            ->where('person_id', $personId)
            ->orderBy('convocation_id')
            ->get()
            ->keyBy('convocation_id');
        $firstDeputy = $termDeputies->get($firstConvocationId);
        $secondDeputy = $termDeputies->get($secondConvocationId);

        expect($termDeputies)->toHaveCount(2)
            ->and($firstDeputy->voting_identifier)->toBe('TERM-001')
            ->and($firstDeputy->political_party_id)->toBe($firstPartyId)
            ->and($firstDeputy->start_date)->toBe('2020-11-01')
            ->and($secondDeputy->voting_identifier)->toBe('TERM-002')
            ->and($secondDeputy->political_party_id)->toBe($secondPartyId)
            ->and($secondDeputy->start_date)->toBe('2025-11-01')
            ->and($firstDeputy->homepage)->toBe('https://council.example.test/ivan')
            ->and($secondDeputy->homepage)->toBe('https://council.example.test/ivan');

        expect(DB::table('votes')->where('id', $voteIds[0])->value('deputy_id'))->toBe($firstDeputy->id)
            ->and(DB::table('votes')->where('id', $voteIds[1])->value('deputy_id'))->toBe($secondDeputy->id)
            ->and(DB::table('votes')->whereIn('id', $voteIds)->orderBy('id')->pluck('voter_identifier')->all())
            ->toBe(['SNAPSHOT-0', 'SNAPSHOT-1'])
            ->and(DB::table('deputy_receptions')->count())->toBe(1)
            ->and(DB::table('deputy_receptions')->value('deputy_id'))->toBe($firstDeputy->id)
            ->and(DB::table('contact_points')->value('contactable_id'))->toBe($firstDeputy->id)
            ->and(Schema::hasTable('memberships'))->toBeFalse()
            ->and(Schema::hasColumn('people', 'homepage'))->toBeFalse()
            ->and(Schema::hasColumn('deputies', 'organization_id'))->toBeFalse();

        $migration->down();

        expect(DB::table('memberships')->count())->toBe(2)
            ->and(DB::table('memberships')->pluck('deputy_id')->unique()->all())->toBe([$deputyId])
            ->and(DB::table('deputies')->where('person_id', $personId)->count())->toBe(1)
            ->and(DB::table('votes')->whereIn('id', $voteIds)->pluck('deputy_id')->unique()->all())->toBe([$deputyId])
            ->and(DB::table('people')->where('id', $personId)->value('homepage'))
            ->toBe('https://council.example.test/ivan')
            ->and(Schema::hasColumn('people', 'homepage'))->toBeTrue()
            ->and(Schema::hasColumn('deputies', 'homepage'))->toBeFalse();

        DB::table('memberships')->insert([
            'deputy_id' => $deputyId,
            'organization_id' => $organizationId,
            'convocation_id' => null,
            'role' => 'Старе членство без скликання',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, 'organization_id is not retained');
        expect(Schema::hasTable('memberships'))->toBeTrue()
            ->and(Schema::hasColumn('deputies', 'homepage'))->toBeFalse();
    } finally {
        config(['database.default' => $defaultConnection]);
        DB::purge($connectionName);
    }
});

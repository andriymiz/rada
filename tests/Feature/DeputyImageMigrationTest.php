<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function useDeputyImageMigrationConnection(): string
{
    $connectionName = 'deputy_image_migration_test';

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

    $schema = Schema::connection($connectionName);
    $schema->create('people', function (Blueprint $table): void {
        $table->id();
        $table->text('image')->nullable();
    });
    $schema->create('deputies', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('person_id');
        $table->unsignedBigInteger('convocation_id');
        $table->unique(['person_id', 'convocation_id']);
    });

    return $connectionName;
}

function restoreDeputyImageMigrationConnection(string $connectionName, string $defaultConnection): void
{
    DB::purge($connectionName);
    config(['database.default' => $defaultConnection]);
}

it('moves each person image to their deputy records and restores it when rolling back', function () {
    $defaultConnection = config('database.default');
    $connectionName = useDeputyImageMigrationConnection();

    try {
        $personId = DB::table('people')->insertGetId(['image' => 'https://example.test/person.jpg']);
        DB::table('deputies')->insert([
            ['person_id' => $personId, 'convocation_id' => 1],
            ['person_id' => $personId, 'convocation_id' => 2],
        ]);
        $migration = require database_path('migrations/2026_10_15_181842_move_image_from_people_to_deputies.php');

        $migration->up();

        expect(Schema::hasColumn('people', 'image'))->toBeFalse()
            ->and(Schema::hasColumn('deputies', 'image'))->toBeTrue()
            ->and(DB::table('deputies')->where('person_id', $personId)->orderBy('id')->pluck('image')->all())
            ->toBe([
                'https://example.test/person.jpg',
                'https://example.test/person.jpg',
            ]);

        $migration->down();

        expect(Schema::hasColumn('people', 'image'))->toBeTrue()
            ->and(Schema::hasColumn('deputies', 'image'))->toBeFalse()
            ->and(DB::table('people')->where('id', $personId)->value('image'))
            ->toBe('https://example.test/person.jpg');
    } finally {
        restoreDeputyImageMigrationConnection($connectionName, $defaultConnection);
    }
});

it('preserves deputy images when rollback cannot merge different images for one person', function () {
    $defaultConnection = config('database.default');
    $connectionName = useDeputyImageMigrationConnection();

    try {
        $personId = DB::table('people')->insertGetId(['image' => 'https://example.test/person.jpg']);
        DB::table('deputies')->insert([
            ['person_id' => $personId, 'convocation_id' => 1],
            ['person_id' => $personId, 'convocation_id' => 2],
        ]);
        $migration = require database_path('migrations/2026_10_15_181842_move_image_from_people_to_deputies.php');

        $migration->up();
        DB::table('deputies')->where('convocation_id', 1)->update(['image' => 'https://example.test/first.jpg']);
        DB::table('deputies')->where('convocation_id', 2)->update(['image' => null]);

        expect(fn () => $migration->down())
            ->toThrow(RuntimeException::class, 'Cannot restore person images');

        expect(Schema::hasColumn('deputies', 'image'))->toBeTrue()
            ->and(Schema::hasColumn('people', 'image'))->toBeFalse()
            ->and(DB::table('deputies')->where('person_id', $personId)->orderBy('id')->pluck('image')->all())
            ->toBe([
                'https://example.test/first.jpg',
                null,
            ]);
    } finally {
        restoreDeputyImageMigrationConnection($connectionName, $defaultConnection);
    }
});

it('does not discard an image when a person has no deputy record', function () {
    $defaultConnection = config('database.default');
    $connectionName = useDeputyImageMigrationConnection();

    try {
        $personId = DB::table('people')->insertGetId(['image' => 'https://example.test/orphan.jpg']);
        $migration = require database_path('migrations/2026_10_15_181842_move_image_from_people_to_deputies.php');

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, 'some people do not have a deputy record');

        expect(Schema::hasColumn('people', 'image'))->toBeTrue()
            ->and(Schema::hasColumn('deputies', 'image'))->toBeFalse()
            ->and(DB::table('people')->where('id', $personId)->value('image'))
            ->toBe('https://example.test/orphan.jpg');
    } finally {
        restoreDeputyImageMigrationConnection($connectionName, $defaultConnection);
    }
});

<?php

use App\Enums\Gender;
use App\Models\Deputy;
use Illuminate\Support\Facades\DB;

it('moves existing deputy profile data to people without changing biography', function () {
    $deputyTermsMigration = require database_path('migrations/2026_10_11_201319_move_membership_fields_to_deputies.php');
    $peopleMigration = require database_path('migrations/2026_10_11_190654_create_people_table_and_link_deputies.php');
    $votingIdentifierMigration = require database_path('migrations/2026_10_13_205434_drop_voting_identifier_unique_from_deputies_table.php');
    $deputyImageMigration = require database_path('migrations/2026_10_15_181842_move_image_from_people_to_deputies.php');
    $deputyImageMigration->down();
    $votingIdentifierMigration->down();
    $deputyTermsMigration->down();
    $peopleMigration->down();

    DB::table('deputies')->insert([
        'last_name' => 'Іваненко',
        'first_name' => 'Іван',
        'middle_name' => 'Іванович',
        'gender' => Gender::Male->value,
        'date_of_birth' => '1980-01-02',
        'image' => 'https://example.test/ivan.jpg',
        'homepage' => 'https://council.example.test/ivan',
        'biography' => 'Біографія саме депутата.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $peopleMigration->up();
    $deputyTermsMigration->up();
    $votingIdentifierMigration->up();

    $deputy = Deputy::query()
        ->with('person')
        ->where('biography', 'Біографія саме депутата.')
        ->firstOrFail();

    $this->assertModelExists($deputy->person);

    expect($deputy->person->only([
        'last_name',
        'first_name',
        'middle_name',
        'gender',
        'image',
    ]))->toMatchArray([
        'last_name' => 'Іваненко',
        'first_name' => 'Іван',
        'middle_name' => 'Іванович',
        'gender' => Gender::Male,
        'image' => 'https://example.test/ivan.jpg',
    ])
        ->and($deputy->person->date_of_birth?->toDateString())->toBe('1980-01-02')
        ->and($deputy->homepage)->toBe('https://council.example.test/ivan')
        ->and($deputy->biography)->toBe('Біографія саме депутата.');
});

<?php

use App\Enums\Gender;
use App\Filament\Imports\OfficialImporter;
use App\Models\Official;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('imports an official from the posts csv row', function (): void {
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА ТЕРНОПІЛЬСЬКОГО РАЙОНУ ТЕРНОПІЛЬСЬКОЇ ОБЛАСТІ',
    ]);

    OfficialImporter::test()
        ->import([
            'name' => 'Максимів Руслан Сергійович',
            'gender' => '1',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'ЗБОРІВСЬКА МІСЬКА РАДА ТЕРНОПІЛЬСЬКОГО РАЙОНУ ТЕРНОПІЛЬСЬКОЇ ОБЛАСТІ',
            'img' => 'https://example.com/photo.jpg',
            'homepage' => 'https://example.com/official',
            'socialAccount' => 'null',
            'phone' => '+380354021743',
            'email' => 'rada@example.com',
            'unitId' => 'null',
            'unitName' => 'null',
            'openingHours' => 'Пн 09:00-12:00',
            'availabilityRestriction' => 'null',
        ])
        ->assertImported();

    $person = Person::query()
        ->where('last_name', 'Максимів')
        ->where('first_name', 'Руслан')
        ->where('middle_name', 'Сергійович')
        ->firstOrFail();
    $official = Official::query()->whereBelongsTo($person)->firstOrFail();

    expect($person->last_name)->toBe('Максимів')
        ->and($person->first_name)->toBe('Руслан')
        ->and($person->middle_name)->toBe('Сергійович')
        ->and($person->gender)->toBe(Gender::Male)
        ->and($official->organization->is($organization))->toBeTrue()
        ->and($official->position)->toBe('Міський голова')
        ->and($official->image)->toBe('https://example.com/photo.jpg')
        ->and($official->homepage)->toBe('https://example.com/official');

    expect($official->email)->toBe('rada@example.com')
        ->and($official->phone)->toBe('+380354021743')
        ->and($official->opening_hours)->toBe('Пн 09:00-12:00')
        ->and($official->availability_restriction)->toBeNull();
});

it('treats string null values as absent without changing other strings', function (string $nullValue): void {
    Organization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'img' => $nullValue,
            'homepage' => 'https://example.com/null',
            'socialAccount' => $nullValue,
            'phone' => '+380501234567',
            'email' => 'official@example.com',
        ])
        ->assertImported();

    $official = Official::query()->sole();

    expect($official->image)->toBeNull()
        ->and($official->homepage)->toBe('https://example.com/null')
        ->and($official->social_accounts)->toBeEmpty()
        ->and($official->phone)->toBe('+380501234567')
        ->and($official->email)->toBe('official@example.com');
})->with([
    'lowercase' => ['null'],
    'mixed case with whitespace' => [" \tNuLl \n"],
]);

it('treats a null homepage as absent', function (): void {
    Organization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'homepage' => ' NULL ',
            'phone' => '+380501234567',
            'email' => 'official@example.com',
        ])
        ->assertImported();

    expect(Official::query()->sole()->homepage)->toBeNull();
});

it('requires a primary email and telephone when importing an official', function (): void {
    Organization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'email' => 'null',
            'phone' => '',
        ])
        ->assertHasErrors(['email' => 'required', 'phone' => 'required']);

    expect(Official::query()->exists())->toBeFalse();
});

it('rejects comma-separated contacts in singular fields', function (): void {
    Organization::factory()->create(['edrpou' => '12345678']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'email' => ', office@example.com',
            'phone' => '+380501234567, ',
        ])
        ->assertHasErrors(['email', 'phone']);
    expect(Official::query()->exists())->toBeFalse();
});

it('rejects multiple email addresses and telephone numbers in singular fields', function (): void {
    Organization::factory()->create(['edrpou' => '12345678']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'email' => 'first@example.com,second@example.com',
            'phone' => '+380501234567,+380501234568',
        ])
        ->assertHasErrors(['email', 'phone']);

    expect(Official::query()->exists())->toBeFalse();
});

it('imports multiple social accounts from an officials csv row', function (): void {
    Organization::factory()->create(['edrpou' => '12345678']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'socialAccount' => 'https://facebook.com/koval, https://instagram.com/koval',
            'phone' => '+380501234567',
            'email' => 'official@example.com',
        ])
        ->assertImported();

    expect(Official::query()->sole()->social_accounts)->toBe([
        'https://facebook.com/koval',
        'https://instagram.com/koval',
    ]);
});

it('rejects a null string in the singular email field', function (): void {
    Organization::factory()->create(['edrpou' => '12345678']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'email' => 'first@example.com,null',
        ])
        ->assertHasErrors(['email' => 'email']);

    expect(Official::query()->count())->toBe(0);
});

it('imports names with missing or additional parts', function (string $name, string $lastName, ?string $firstName, ?string $middleName): void {
    Organization::factory()->create(['edrpou' => '12345678']);

    OfficialImporter::test()
        ->import([
            'name' => $name,
            'gender' => '1',
            'positionName' => 'Міський голова',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'phone' => '+380501234567',
            'email' => 'official@example.com',
        ])
        ->assertImported();

    $person = Official::query()->sole()->person;

    expect($person->last_name)->toBe($lastName)
        ->and($person->first_name)->toBe($firstName)
        ->and($person->middle_name)->toBe($middleName);
})->with([
    'one part' => ['Коваль', 'Коваль', null, null],
    'two parts' => ['Коваль Олена', 'Коваль', 'Олена', null],
    'multiple whitespace characters' => ["  Коваль\tОлена   Іванівна  ", 'Коваль', 'Олена', 'Іванівна'],
    'additional parts' => ["Коваль Олена Анна\t Іванівна", 'Коваль', 'Олена', 'Анна Іванівна'],
]);

it('updates the matching official in the requested organization', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $otherOrganization = Organization::factory()->create();
    $name = ['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null];
    $otherPerson = Person::factory()->create($name);
    Person::factory()->create();
    $person = Person::factory()->create($name);
    $otherOfficial = Official::factory()->for($otherPerson)->for($otherOrganization, 'organization')->create(['position' => 'Інша посада']);
    $official = Official::factory()->for($person)->for($organization, 'organization')->create([
        'position' => 'Попередня посада',
        'email' => 'old@example.com',
        'phone' => '+380501112233',
        'social_accounts' => ['https://example.com/old'],
        'opening_hours' => 'Старий графік',
        'availability_restriction' => 'Старі обмеження',
    ]);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Нова посада',
            'orgId' => '12345678',
            'orgName' => 'Міська рада',
            'email' => 'new@example.com',
            'phone' => '+380674445566',
            'socialAccount' => 'https://example.com/new',
            'openingHours' => 'Новий графік',
            'availabilityRestriction' => 'Нові обмеження',
        ])
        ->assertImported();

    expect(Official::query()->count())->toBe(2)
        ->and($official->refresh()->position)->toBe('Нова посада')
        ->and($official->email)->toBe('new@example.com')
        ->and($official->phone)->toBe('+380674445566')
        ->and($official->social_accounts)->toBe(['https://example.com/new'])
        ->and($official->opening_hours)->toBe('Новий графік')
        ->and($official->availability_restriction)->toBe('Нові обмеження')
        ->and($official->person_id)->toBe($person->id)
        ->and($otherOfficial->refresh()->position)->toBe('Інша посада');
});

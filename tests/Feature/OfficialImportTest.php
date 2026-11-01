<?php

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Filament\Imports\OfficialImporter;
use App\Filament\Resources\Officials\Pages\ListOfficials;
use App\Models\CouncilOrganization;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('imports an official from the posts csv row', function (): void {
    $organization = CouncilOrganization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА ТЕРНОПІЛЬСЬКОГО РАЙОНУ ТЕРНОПІЛЬСЬКОЇ ОБЛАСТІ',
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
            'telephone' => '380354021743',
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

    expect($official->contactPoints)->toHaveCount(2);
    expect($official->contactPoints->firstWhere('type', ContactPointType::Email)->value)
        ->toBe('rada@example.com');
    expect($official->contactPoints->firstWhere('type', ContactPointType::Phone)->value)
        ->toBe('+380354021743');
});

it('renders the csv import action on the officials list', function (): void {
    livewire(ListOfficials::class)
        ->assertActionExists('import');
});

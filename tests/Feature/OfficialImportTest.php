<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceType;
use App\Enums\Gender;
use App\Filament\Imports\OfficialImporter;
use App\Filament\Resources\Officials\Pages\ListOfficials;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Models\Official;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

it('treats string null values as absent without changing other strings', function (string $nullValue): void {
    CouncilOrganization::factory()->create(['edrpou' => '04058410']);

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
            'telephone' => $nullValue,
            'email' => $nullValue,
        ])
        ->assertImported();

    $official = Official::query()->sole();

    expect($official->image)->toBeNull()
        ->and($official->homepage)->toBe('https://example.com/null')
        ->and($official->contactPoints)->toBeEmpty();
})->with([
    'lowercase' => ['null'],
    'mixed case with whitespace' => [" \tNuLl \n"],
]);

it('treats a null homepage as absent', function (): void {
    CouncilOrganization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'homepage' => ' NULL ',
        ])
        ->assertImported();

    expect(Official::query()->sole()->homepage)->toBeNull();
});

it('imports multiple contact values without changing them during normalization', function (): void {
    CouncilOrganization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'email' => 'first@example.com,second@example.com',
            'telephone' => '380501234567,380501234568',
        ])
        ->assertImported();

    $contacts = Official::query()->sole()->contactPoints;

    expect($contacts->pluck('value')->sort()->values()->all())->toBe([
        '+380501234567',
        '+380501234568',
        'first@example.com',
        'second@example.com',
    ]);
});

it('rejects a null string inside a list of email addresses', function (): void {
    CouncilOrganization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
            'email' => 'first@example.com,null',
        ])
        ->assertHasErrors(['email.1' => 'email']);

    expect(Official::query()->count())->toBe(0);
});

it('imports names with missing or additional parts', function (string $name, string $lastName, ?string $firstName, ?string $middleName): void {
    CouncilOrganization::factory()->create(['edrpou' => '04058410']);

    OfficialImporter::test()
        ->import([
            'name' => $name,
            'gender' => '1',
            'positionName' => 'Міський голова',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
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
    $organization = CouncilOrganization::factory()->create(['edrpou' => '04058410']);
    $otherOrganization = CouncilOrganization::factory()->create();
    $name = ['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null];
    $otherPerson = Person::factory()->create($name);
    Person::factory()->create();
    $person = Person::factory()->create($name);
    $otherOfficial = Official::factory()->for($otherPerson)->for($otherOrganization, 'organization')->create(['position' => 'Інша посада']);
    $official = Official::factory()->for($person)->for($organization, 'organization')->create(['position' => 'Попередня посада']);

    OfficialImporter::test()
        ->import([
            'name' => 'Коваль Олена',
            'gender' => '2',
            'positionName' => 'Нова посада',
            'orgId' => '04058410',
            'orgName' => 'Міська рада',
        ])
        ->assertImported();

    expect(Official::query()->count())->toBe(2)
        ->and($official->refresh()->position)->toBe('Нова посада')
        ->and($official->person_id)->toBe($person->id)
        ->and($otherOfficial->refresh()->position)->toBe('Інша посада');
});

it('renders the csv import action on the officials list', function (): void {
    livewire(ListOfficials::class)
        ->assertActionExists('import');
});

it('links the officials importer to the configured open data resource', function (): void {
    DatasetResource::factory()
        ->for(Dataset::factory()->state(['external_id' => 'officials-package-id']))
        ->create([
            'external_id' => 'officials-resource-id',
            'type' => DatasetResourceType::Posts,
        ]);

    $downloadUrl = 'https://data.gov.ua/dataset/officials/resource/officials.csv';

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'resources' => [
                    [
                        'id' => 'officials-resource-id',
                        'name' => 'Посадовці',
                        'url' => $downloadUrl,
                    ],
                ],
            ],
        ]),
    ]);

    livewire(ListOfficials::class)
        ->assertActionExists(
            'import',
            function (Action $action) use ($downloadUrl): bool {
                $downloadAction = $action->getModalAction('downloadExample');

                return $downloadAction?->getLabel() === 'Завантажити актуальний CSV з порталу відкритих даних'
                    && $downloadAction->isLink()
                    && $downloadAction->getUrl() === $downloadUrl
                    && $downloadAction->shouldOpenUrlInNewTab();
            },
        );

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'officials-package-id');
});

it('keeps the built-in example download when no officials resource is configured', function (): void {
    Http::preventStrayRequests();

    livewire(ListOfficials::class)
        ->assertActionExists(
            'import',
            function (Action $action): bool {
                $downloadAction = $action->getModalAction('downloadExample');

                return $downloadAction?->hasAction() === true
                    && $downloadAction->getUrl() === null;
            },
        );
});

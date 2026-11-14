<?php

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceType;
use App\Enums\DeputyStatus;
use App\Enums\Gender;
use App\Filament\Imports\DeputyImporter;
use App\Filament\Resources\Convocations\Pages\EditConvocation;
use App\Filament\Resources\Convocations\RelationManagers\DeputiesRelationManager;
use App\Models\Convocation;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Models\Deputy;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('imports a deputy with open data attributes and related entities', function () {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 23,
            'last_name' => 'Ковальчук',
            'first_name' => 'Віктор',
            'middle_name' => 'Зосимович',
            'gender' => 1,
            'date_of_birth' => '1980-12-01',
            'image' => 'https://example.gov.ua/photo.png',
            'biography' => 'Біографічні дані депутата.',
            'partyName' => 'Партія розвитку',
            'partyId' => '12345678',
            'email' => 'kovalchuk@example.gov.ua',
            'telephone' => '+380501234567',
            'homepage' => 'https://council.example.gov.ua/deputy/kovalchuk',
            'account' => 'https://facebook.com/kovalchuk',
            'factionName' => 'Фракція розвитку',
            'factionPost' => 'Голова фракції',
            'commissionName' => 'Бюджетна комісія',
            'commissionPost' => 'Секретар',
            'area_id' => 4,
            'voting_identifier' => 'dep-23',
            'start_date' => '2020-12-01',
        ])
        ->assertImported();

    $deputy = Deputy::query()
        ->where('convocation_id', $convocation->id)
        ->firstOrFail();

    expect($deputy->person)->not->toBeNull()
        ->and($deputy->person->last_name)->toBe('Ковальчук')
        ->and($deputy->person->first_name)->toBe('Віктор')
        ->and($deputy->person->middle_name)->toBe('Зосимович')
        ->and($deputy->person->gender)->toBe(Gender::Male)
        ->and($deputy->person->date_of_birth->toDateString())->toBe('1980-12-01')
        ->and($deputy->number)->toBe(23)
        ->and($deputy->image)->toBe('https://example.gov.ua/photo.png')
        ->and($deputy->biography)->toBe('Біографічні дані депутата.')
        ->and($deputy->status)->toBe(DeputyStatus::Active)
        ->and($deputy->voting_identifier)->toBe('dep-23')
        ->and($deputy->start_date?->toDateString())->toBe('2020-12-01')
        ->and($deputy->area_id)->toBe(4)
        ->and($deputy->homepage)->toBe('https://council.example.gov.ua/deputy/kovalchuk')
        ->and($deputy->politicalParty->name)->toBe('Партія розвитку')
        ->and($deputy->politicalParty->edrpou)->toBe('12345678')
        ->and($deputy->faction->name)->toBe('Фракція розвитку')
        ->and($deputy->faction_position)->toBe('Голова фракції')
        ->and($deputy->permanentCommission->name)->toBe('Бюджетна комісія')
        ->and($deputy->commission_position)->toBe('Секретар')
        ->and($deputy->reception)->toBeNull();

    $contacts = $deputy->contactPoints;
    expect($contacts)->toHaveCount(3);

    $email = $contacts->firstWhere('type', ContactPointType::Email);
    expect($email)->not->toBeNull()
        ->and($email->value)->toBe('kovalchuk@example.gov.ua')
        ->and($email->is_public)->toBeTrue();

    $phone = $contacts->firstWhere('type', ContactPointType::Phone);
    expect($phone)->not->toBeNull()
        ->and($phone->value)->toBe('+380501234567')
        ->and($phone->is_public)->toBeTrue();

    $social = $contacts->firstWhere('type', ContactPointType::Facebook);
    expect($social)->not->toBeNull()
        ->and($social->value)->toBe('https://facebook.com/kovalchuk')
        ->and($social->is_public)->toBeTrue();
});

it('updates the start date and preserves it when the imported date is blank', function (): void {
    $convocation = Convocation::factory()->create();
    $deputy = Deputy::factory()->for($convocation, 'convocation')->create([
        'number' => 23,
        'start_date' => '2020-01-01',
    ]);
    $row = [
        'number' => 23,
        'last_name' => 'Ковальчук',
        'first_name' => 'Віктор',
        'middle_name' => 'Зосимович',
        'gender' => 1,
        'date_of_birth' => '1980-12-01',
        'biography' => 'Біографія',
        'partyName' => 'Партія розвитку',
        'start_date' => '2021-03-15',
    ];

    DeputyImporter::test(options: ['convocation' => $convocation])
        ->import($row)
        ->assertImported();

    expect($deputy->refresh()->start_date?->toDateString())->toBe('2021-03-15');

    $row['start_date'] = ' NULL ';

    DeputyImporter::test(options: ['convocation' => $convocation])
        ->import($row)
        ->assertImported();

    expect($deputy->refresh()->start_date?->toDateString())->toBe('2021-03-15');
});

it('rejects invalid start dates without saving the deputy', function (): void {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(options: ['convocation' => $convocation])
        ->import([
            'number' => 23,
            'last_name' => 'Ковальчук',
            'first_name' => 'Віктор',
            'middle_name' => 'Зосимович',
            'gender' => 1,
            'date_of_birth' => '1980-12-01',
            'biography' => 'Біографія',
            'partyName' => 'Партія розвитку',
            'start_date' => 'not-a-date',
        ])
        ->assertHasErrors(['start_date' => 'date']);

    expect(Deputy::query()->whereBelongsTo($convocation)->exists())->toBeFalse();
});

it('links the deputy importer to the configured open data resource', function (): void {
    $convocation = Convocation::factory()->create();
    DatasetResource::factory()
        ->for(Dataset::factory()->state(['external_id' => 'deputies-package-id']))
        ->create([
            'external_id' => 'deputies-resource-id',
            'name' => DatasetResourceType::Deputies,
        ]);

    $downloadUrl = 'https://data.gov.ua/dataset/deputies/resource/deputies.csv';

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'deputies-package-id',
                'resources' => [
                    [
                        'id' => 'deputies-resource-id',
                        'name' => 'Депутати',
                        'url' => $downloadUrl,
                    ],
                ],
            ],
        ]),
    ]);

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->assertTableActionExists(
            'import',
            function (Action $action) use ($downloadUrl): bool {
                $downloadAction = $action->getModalAction('downloadExample');

                return $downloadAction?->getLabel() === 'Завантажити актуальний ресурс з порталу відкритих даних'
                    && $downloadAction->isLink()
                    && $downloadAction->getUrl() === $downloadUrl
                    && $downloadAction->shouldOpenUrlInNewTab();
            },
        );

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'deputies-package-id');
});

it('keeps the built-in example download when no deputy resource is configured', function (): void {
    $convocation = Convocation::factory()->create();

    Http::preventStrayRequests();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->assertTableActionExists(
            'import',
            function (Action $action): bool {
                $downloadAction = $action->getModalAction('downloadExample');

                return $downloadAction?->hasAction() === true
                    && $downloadAction->getUrl() === null;
            },
        );
});

it('handles nullable optional values with Filament default casting', function () {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 1,
            'last_name' => 'Шевченко',
            'first_name' => 'Олена',
            'middle_name' => 'Іванівна',
            'gender' => '2',
            'date_of_birth' => '1992-05-15',
            'image' => null,
            'biography' => 'Біографічні дані Олени Шевченко.',
            'partyName' => 'Партія Шевченка',
            'partyId' => 'null',
            'email' => 'null',
            'telephone' => 'null',
            'homepage' => 'null',
            'account' => 'null',
            'factionName' => 'Позафракційний',
            'factionPost' => null,
            'commissionName' => null,
            'commissionPost' => null,
            'area_id' => null,
            'voting_identifier' => null,
        ])
        ->assertImported();

    $deputy = Deputy::query()
        ->where('convocation_id', $convocation->id)
        ->firstOrFail();

    expect($deputy->person->last_name)->toBe('Шевченко')
        ->and($deputy->person->gender)->toBe(Gender::Female)
        ->and($deputy->image)->toBeNull()
        ->and($deputy->biography)->toBe('Біографічні дані Олени Шевченко.')
        ->and($deputy->politicalParty->name)->toBe('Партія Шевченка')
        ->and($deputy->politicalParty->edrpou)->toBeNull()
        ->and($deputy->faction)->toBeNull()
        ->and($deputy->faction_position)->toBeNull()
        ->and($deputy->permanentCommission)->toBeNull()
        ->and($deputy->commission_position)->toBeNull()
        ->and($deputy->area_id)->toBeNull()
        ->and($deputy->voting_identifier)->toBeNull()
        ->and($deputy->homepage)->toBeNull()
        ->and($deputy->reception)->toBeNull()
        ->and($deputy->contactPoints)->toBeEmpty();
});

it('normalizes mixed-case null values while preserving other contact values', function (): void {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(options: ['convocation' => $convocation])
        ->import([
            'number' => '1',
            'last_name' => 'Шевченко',
            'first_name' => 'Олена',
            'middle_name' => 'Іванівна',
            'gender' => '2',
            'date_of_birth' => '1992-05-15',
            'biography' => 'Біографія',
            'partyName' => 'Партія',
            'partyId' => ' NuLl ',
            'homepage' => ' NULL ',
            'area_id' => ' null ',
            'email' => ' NuLl ',
            'account' => ' NULL ',
            'telephone' => '380501234567,380501234568',
        ])
        ->assertImported();

    $deputy = Deputy::query()->sole();

    expect($deputy->politicalParty->edrpou)->toBeNull()
        ->and($deputy->homepage)->toBeNull()
        ->and($deputy->area_id)->toBeNull()
        ->and($deputy->contactPoints->pluck('value')->sort()->values()->all())->toBe([
            '+380501234567',
            '+380501234568',
        ]);
});

it('updates existing deputy and person on repeated import', function () {
    $convocation = Convocation::factory()->create();
    $person = Person::factory()->create([
        'last_name' => 'Мельник',
        'first_name' => 'Андрій',
        'middle_name' => 'Петрович',
        'date_of_birth' => '1975-08-20',
    ]);
    $deputy = Deputy::factory()->create([
        'convocation_id' => $convocation->id,
        'person_id' => $person->id,
        'biography' => 'Стара біографія',
        'number' => 1,
        'voting_identifier' => 'dep-01',
    ]);

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 1,
            'last_name' => 'Мельник',
            'first_name' => 'Андрій',
            'middle_name' => 'Петрович',
            'partyName' => 'Партія Мельника',
            'gender' => 1,
            'date_of_birth' => '1975-08-20',
            'biography' => 'Оновлена біографія депутата.',
            'voting_identifier' => 'dep-01-new',
            'image' => 'https://example.gov.ua/photo-updated.png',
            'homepage' => 'https://example.gov.ua/deputies/melnyk',
        ])
        ->assertImported();

    expect(Deputy::query()->where('convocation_id', $convocation->id)->count())->toBe(1)
        ->and(Person::query()->count())->toBe(1);

    $deputy->refresh();
    expect($deputy->biography)->toBe('Оновлена біографія депутата.')
        ->and($deputy->voting_identifier)->toBe('dep-01-new')
        ->and($deputy->image)->toBe('https://example.gov.ua/photo-updated.png')
        ->and($deputy->homepage)->toBe('https://example.gov.ua/deputies/melnyk');
});

it('matches a deputy by its convocation number when the imported name changes', function () {
    $convocation = Convocation::factory()->create();
    $targetPerson = Person::factory()->create([
        'last_name' => 'Мельник',
        'first_name' => 'Андрій',
        'middle_name' => 'Петрович',
    ]);
    $targetDeputy = Deputy::factory()->create([
        'convocation_id' => $convocation->id,
        'person_id' => $targetPerson->id,
        'number' => 23,
        'biography' => 'Стара біографія цільового депутата.',
    ]);
    $otherDeputy = Deputy::factory()->create([
        'convocation_id' => $convocation->id,
        'number' => 24,
    ]);

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 23,
            'last_name' => 'Ковальчук',
            'first_name' => 'Олена',
            'middle_name' => 'Іванівна',
            'partyName' => 'Партія Мельника',
            'gender' => 2,
            'date_of_birth' => '1990-04-15',
            'biography' => 'Оновлена біографія цільового депутата.',
        ])
        ->assertImported();

    expect(Deputy::query()->where('convocation_id', $convocation->id)->count())->toBe(2)
        ->and($targetDeputy->fresh()->biography)->toBe('Оновлена біографія цільового депутата.')
        ->and($targetDeputy->fresh()->number)->toBe(23)
        ->and($targetDeputy->fresh()->person->full_name)->toBe('Ковальчук Олена Іванівна')
        ->and($otherDeputy->fresh()->biography)->not->toBe('Оновлена біографія цільового депутата.')
        ->and($otherDeputy->fresh()->person->full_name)->not->toBe('Ковальчук Олена Іванівна');
});

it('reuses a deputy person across convocations without duplicating them within a convocation', function () {
    $firstConvocation = Convocation::factory()->create();
    $secondConvocation = Convocation::factory()->create();
    $person = Person::factory()->create([
        'last_name' => 'Кравченко',
        'first_name' => 'Ірина',
        'middle_name' => 'Олегівна',
        'date_of_birth' => '1984-03-12',
    ]);
    $firstTermDeputy = Deputy::factory()->create([
        'convocation_id' => $firstConvocation->id,
        'person_id' => $person->id,
        'number' => 8,
    ]);

    $row = [
        'number' => 8,
        'last_name' => 'Кравченко',
        'first_name' => 'Ірина',
        'middle_name' => 'Олегівна',
        'partyName' => 'Партія Кравченко',
        'gender' => 2,
        'date_of_birth' => '1985-03-12',
        'biography' => 'Оновлена біографія депутата.',
    ];

    DeputyImporter::test(
        options: ['convocation' => $secondConvocation],
    )
        ->import($row)
        ->assertImported();

    DeputyImporter::test(
        options: ['convocation' => $secondConvocation],
    )
        ->import($row)
        ->assertImported();

    $secondTermDeputies = Deputy::query()
        ->where('convocation_id', $secondConvocation->id)
        ->get();

    expect($secondTermDeputies)->toHaveCount(1)
        ->and($firstTermDeputy->fresh()->person_id)->toBe($person->id)
        ->and($secondTermDeputies->first()->person_id)->toBe($person->id)
        ->and(Deputy::query()->where('person_id', $person->id)->count())->toBe(2)
        ->and($person->fresh()->date_of_birth->toDateString())->toBe('1985-03-12');
});

it('allows voting identifiers to repeat across deputy records', function () {
    $convocation = Convocation::factory()->create();
    Deputy::factory()->create([
        'convocation_id' => $convocation->id,
        'number' => 1,
        'voting_identifier' => 'taken-vote-id',
    ]);

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 2,
            'last_name' => 'Сидоренко',
            'first_name' => 'Сергій',
            'middle_name' => 'Олексійович',
            'partyName' => 'Партія Сидоренка',
            'gender' => 1,
            'date_of_birth' => '1982-06-05',
            'biography' => 'Біографічні дані Сергія Сидоренка.',
            'voting_identifier' => 'taken-vote-id',
        ])
        ->assertImported();

    expect(Deputy::query()->where('convocation_id', $convocation->id)->count())->toBe(2)
        ->and(Deputy::query()
            ->where('convocation_id', $convocation->id)
            ->where('voting_identifier', 'taken-vote-id')
            ->count())->toBe(2);
});

it('parses multiple comma-separated contact points', function () {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 3,
            'last_name' => 'Бондар',
            'first_name' => 'Марія',
            'middle_name' => 'Іванівна',
            'gender' => 2,
            'date_of_birth' => '1988-03-21',
            'biography' => 'Біографічні дані Марії Бондар.',
            'partyName' => 'Партія Бондар',
            'email' => 'bondar1@example.gov.ua, bondar2@example.gov.ua',
            'telephone' => '+380501112233, ‘+380674445566',
            'account' => 'https://t.me/mbondar, https://instagram.com/mbondar',
        ])
        ->assertImported();

    $deputy = Deputy::query()->where('convocation_id', $convocation->id)->firstOrFail();
    $contacts = $deputy->contactPoints;

    expect($contacts)->toHaveCount(6);

    $emails = $contacts->where('type', ContactPointType::Email)->pluck('value')->all();
    expect($emails)->toBe(['bondar1@example.gov.ua', 'bondar2@example.gov.ua']);

    $phones = $contacts->where('type', ContactPointType::Phone)->pluck('value')->all();
    expect($phones)->toBe(['+380501112233', '+380674445566']);

    $telegram = $contacts->firstWhere('type', ContactPointType::Telegram);
    expect($telegram?->value)->toBe('https://t.me/mbondar');

    $instagram = $contacts->firstWhere('type', ContactPointType::Instagram);
    expect($instagram?->value)->toBe('https://instagram.com/mbondar');
});

it('rejects an invalid email contact during import validation', function () {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 4,
            'last_name' => 'Романюк',
            'first_name' => 'Тетяна',
            'middle_name' => 'Петрівна',
            'gender' => 2,
            'date_of_birth' => '1985-02-14',
            'biography' => 'Біографічні дані Тетяни Романюк.',
            'partyName' => 'Партія Романюк',
            'email' => 'valid@example.gov.ua,not-an-email',
        ])
        ->assertHasErrors(['email.1' => 'email']);

    expect(Deputy::query()->where('convocation_id', $convocation->id)->exists())->toBeFalse();
});

it('rejects a deputy row without a party name', function () {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 5,
            'last_name' => 'Петренко',
            'first_name' => 'Оксана',
            'middle_name' => 'Іванівна',
            'gender' => 2,
            'date_of_birth' => '1990-01-01',
            'biography' => 'Біографічні дані Оксани Петренко.',
        ])
        ->assertHasErrors(['partyName' => 'required']);

    expect(Deputy::query()->where('convocation_id', $convocation->id)->exists())->toBeFalse();
});

it('rejects a deputy row when a required personal field is missing', function (string $missingField) {
    $convocation = Convocation::factory()->create();
    $row = [
        'last_name' => 'Петренко',
        'first_name' => 'Оксана',
        'middle_name' => 'Іванівна',
        'gender' => 2,
        'date_of_birth' => '1990-01-01',
        'biography' => 'Біографічні дані Оксани Петренко.',
        'partyName' => 'Партія Петренко',
        'number' => 6,
    ];
    unset($row[$missingField]);

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import($row)
        ->assertHasErrors([$missingField => 'required']);

    expect(Deputy::query()->where('convocation_id', $convocation->id)->exists())->toBeFalse();
})->with([
    'patronymic' => 'middle_name',
    'gender' => 'gender',
    'birth date' => 'date_of_birth',
    'biography' => 'biography',
    'number' => 'number',
]);

it('rejects gender values that are not standard numeric codes', function (int|string $gender) {
    $convocation = Convocation::factory()->create();

    DeputyImporter::test(
        options: ['convocation' => $convocation],
    )
        ->import([
            'number' => 7,
            'last_name' => 'Петренко',
            'first_name' => 'Оксана',
            'middle_name' => 'Іванівна',
            'gender' => $gender,
            'date_of_birth' => '1990-01-01',
            'biography' => 'Біографічні дані Оксани Петренко.',
            'partyName' => 'Партія Петренко',
        ])
        ->assertHasErrors(['gender']);

    expect(Deputy::query()->where('convocation_id', $convocation->id)->exists())->toBeFalse();
})->with([
    'unsupported numeric code' => 3,
    'text value' => 'female',
]);

it('guesses the open-data headers for person name columns', function () {
    $columns = collect(DeputyImporter::getColumns())
        ->keyBy(fn ($column): string => $column->getName());

    expect($columns['number']->getGuesses())->toContain('id')
        ->and($columns['last_name']->getGuesses())->toContain('familyname')
        ->and($columns['first_name']->getGuesses())->toContain('name')
        ->and($columns['middle_name']->getGuesses())->toContain('additionalname')
        ->and($columns['start_date']->getGuesses())->toContain('startdate');
});

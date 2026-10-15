<?php

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Filament\Resources\Convocations\Pages\EditConvocation;
use App\Filament\Resources\Convocations\RelationManagers\DeputiesRelationManager;
use App\Models\ContactPoint;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\Deputy;
use App\Models\DeputyReception;
use App\Models\Faction;
use App\Models\Person;
use App\Models\PoliticalParty;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a deputy profile without requiring reception details', function () {
    $convocation = Convocation::factory()->create([
        'label' => 'Дев’яте скликання',
    ]);
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->mountTableAction('create')
        ->callFormComponentAction('person_id', 'createOption', [
            'last_name' => 'Тестове',
            'first_name' => 'Ім’я',
            'middle_name' => 'ПоБатькові',
            'gender' => Gender::Female->value,
            'date_of_birth' => '1985-03-12',
        ], formName: 'mountedActionSchema0')
        ->setTableActionData([
            'political_party_id' => $party->id,
            'homepage' => 'https://council.example.gov.ua/deputies/test',
            'image' => 'https://council.example.gov.ua/photos/test.png',
            'biography' => 'Біографія депутата.',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $deputy = Deputy::query()->with('person')->firstOrFail();

    $this->assertModelExists($deputy);
    $this->assertModelExists($deputy->person);

    expect($deputy->convocation->is($convocation))->toBeTrue()
        ->and($deputy->person->gender)->toBe(Gender::Female)
        ->and($deputy->person->date_of_birth->toDateString())->toBe('1985-03-12')
        ->and($deputy->homepage)->toBe('https://council.example.gov.ua/deputies/test')
        ->and($deputy->image)->toBe('https://council.example.gov.ua/photos/test.png')
        ->and($deputy->full_name)->toBe('Тестове Ім’я ПоБатькові')
        ->and($deputy->voting_identifier)->toBe('1')
        ->and($deputy->reception)->toBeNull()
        ->and($deputy->getAttributes())->not->toHaveKey('last_name')
        ->and($deputy->getAttributes())->not->toHaveKey('name')
        ->and($deputy->getAttributes())->not->toHaveKey('identifier')
        ->and($deputy->getAttributes())->toHaveKey('voting_identifier');

    expect(Schema::hasColumn('deputies', 'last_name'))->toBeFalse()
        ->and(Schema::hasColumn('people', 'last_name'))->toBeTrue();

    expect(Schema::hasColumn('deputies', 'homepage'))->toBeTrue()
        ->and(Schema::hasColumn('people', 'homepage'))->toBeFalse();

    expect($deputy->contactPoints)->toBeEmpty();
});

it('rejects deputy homepage and image values that are not HTTP URLs', function () {
    $person = Person::factory()->create();
    $convocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'homepage' => 'not-a-url',
        'image' => 'not-a-url',
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasTableActionErrors(['homepage', 'image']);

    expect(Deputy::query()->count())->toBe(0)
        ->and(Person::query()->count())->toBe(1);
});

it('links an existing person to a new deputy', function () {
    $person = Person::factory()->create([
        'last_name' => 'Іваненко',
        'first_name' => 'Іван',
        'middle_name' => 'Іванович',
    ]);

    $convocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasNoTableActionErrors();

    $deputy = Deputy::query()->where('person_id', $person->id)->firstOrFail();

    expect($deputy->person->is($person))->toBeTrue()
        ->and($deputy->full_name)->toBe('Іваненко Іван Іванович')
        ->and(Person::query()->count())->toBe(1);
});

it('defaults a new deputy term start date to its convocation start date', function () {
    $convocation = Convocation::factory()->create([
        'date_from' => '2020-01-01',
    ]);
    $person = Person::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasNoTableActionErrors();

    $deputy = Deputy::query()->where('person_id', $person->id)->firstOrFail();

    expect($deputy->start_date?->toDateString())->toBe('2020-01-01');
});

it('defaults a new deputy pult number to one more than the largest numeric identifier', function () {
    $convocation = Convocation::factory()->create();
    Deputy::factory()->for($convocation)->create(['voting_identifier' => '4']);
    Deputy::factory()->for($convocation)->create(['voting_identifier' => '10']);
    Deputy::factory()->for($convocation)->create(['voting_identifier' => 'PULT-100']);
    Deputy::factory()->create(['voting_identifier' => '999']);

    $person = Person::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasNoTableActionErrors();

    $deputy = Deputy::query()->where('person_id', $person->id)->firstOrFail();

    expect($deputy->voting_identifier)->toBe('11');
});

it('rejects linking the same person to two deputy records in one convocation', function () {
    $person = Person::factory()->create();
    $existingDeputy = Deputy::factory()->for($person, 'person')->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $existingDeputy->convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasTableActionErrors(['person_id']);

    expect(Deputy::query()->count())->toBe(1);
});

it('allows one person to have a deputy record in each convocation', function () {
    $person = Person::factory()->create();
    $firstDeputy = Deputy::factory()->for($person, 'person')->create();
    $secondConvocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $secondConvocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія наступного скликання.',
    ])
        ->assertHasNoTableActionErrors();

    expect($person->deputies()->count())->toBe(2)
        ->and($person->deputies()->where('convocation_id', $firstDeputy->convocation_id)->exists())->toBeTrue()
        ->and($person->deputies()->where('convocation_id', $secondConvocation->id)->exists())->toBeTrue();
});

it('shows a deputy name in the relation manager and opens its edit action', function () {
    $deputy = Deputy::factory()
        ->for(Person::factory()->state([
            'last_name' => 'Іваненко',
            'first_name' => 'Іван',
            'middle_name' => 'Іванович',
            'gender' => Gender::Female,
        ]), 'person')
        ->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->assertTableColumnStateSet('short_name', 'Іваненко І. І.', $deputy)
        ->mountTableAction('edit', $deputy);
});

it('formats a deputy short name with initials', function () {
    $deputy = Deputy::factory()
        ->for(Person::factory()->state([
            'last_name' => 'Шевченко',
            'first_name' => 'Богдан',
            'middle_name' => 'Вікторович',
        ]), 'person')
        ->create();

    expect($deputy->short_name)->toBe('Шевченко Б. В.');
});

it('searches deputies by the linked person name', function () {
    $convocation = Convocation::factory()->create();
    $matchingDeputy = Deputy::factory()
        ->for($convocation, 'convocation')
        ->for(Person::factory()->state([
            'last_name' => 'Іваненко',
            'first_name' => 'Іван',
            'middle_name' => 'Іванович',
        ]), 'person')
        ->create();
    $otherDeputy = Deputy::factory()
        ->for($convocation, 'convocation')
        ->for(Person::factory()->state([
            'last_name' => 'Петренко',
            'first_name' => 'Петро',
            'middle_name' => 'Петрович',
        ]), 'person')
        ->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->searchTable('Іваненко')
        ->assertCanSeeTableRecords([$matchingDeputy])
        ->assertCanNotSeeTableRecords([$otherDeputy]);
});

it('stores gender code 9 for a person created from the dropdown', function () {
    $convocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->mountTableAction('create')
        ->callFormComponentAction('person_id', 'createOption', [
            'last_name' => 'Тестове',
            'first_name' => 'Ім’я',
            'middle_name' => 'ПоБатькові',
            'gender' => 9,
            'date_of_birth' => '1985-03-12',
        ], formName: 'mountedActionSchema0')
        ->setTableActionData([
            'political_party_id' => $party->id,
            'biography' => 'Біографія депутата.',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $deputy = Deputy::query()->whereHas('person', fn ($query) => $query->where('last_name', 'Тестове'))->firstOrFail();

    expect($deputy->person->gender)->toBe(Gender::NotApplicable);
});

it('keeps the person and convocation fixed when editing a deputy', function () {
    $deputy = Deputy::factory()
        ->for(PoliticalParty::factory(), 'politicalParty')
        ->create();
    $originalPersonId = $deputy->person_id;
    $originalConvocationId = $deputy->convocation_id;
    $otherPerson = Person::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->callTableAction('edit', $deputy, [
            'person_id' => $otherPerson->id,
            'biography' => 'Оновлена біографія.',
            'image' => 'https://example.gov.ua/photos/updated.png',
        ])
        ->assertHasNoTableActionErrors();

    $deputy->refresh();

    expect($deputy->person_id)->toBe($originalPersonId)
        ->and($deputy->convocation_id)->toBe($originalConvocationId)
        ->and($deputy->biography)->toBe('Оновлена біографія.')
        ->and($deputy->image)->toBe('https://example.gov.ua/photos/updated.png');
});

it('adds reception details only when requested', function () {
    $deputy = Deputy::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->mountTableAction('edit', $deputy)
        ->callFormComponentAction('reception-add-section', 'addReception', [
            'katoottg' => 'UA12345678901234567',
            'address_post_code' => '01001',
            'address_admin_unit_l1' => 'Україна',
            'address_admin_unit_l2' => 'Київська область',
            'address_post_name' => 'Тестове',
            'address_thoroughfare' => 'Тестова',
            'address_locator_designator' => '1',
            'opening_hours' => 'Пн 09:00–18:00',
        ], formName: 'mountedActionSchema0')
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas(DeputyReception::class, [
        'deputy_id' => $deputy->id,
        'katoottg' => 'UA12345678901234567',
        'address_post_name' => 'Тестове',
        'opening_hours' => 'Пн 09:00–18:00',
    ]);
});

it('creates public and private contact points in the deputy edit form', function () {
    $deputy = Deputy::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('edit', $deputy, [
        'political_party_id' => $party->id,
        'contactPoints' => [
            [
                'type' => ContactPointType::Email->value,
                'value' => 'deputy@example.gov.ua',
                'is_public' => true,
                'note' => 'Робоча адреса',
            ],
            [
                'type' => ContactPointType::Phone->value,
                'value' => '+380123456789',
                'is_public' => false,
                'note' => 'Для внутрішнього зв’язку',
            ],
            [
                'type' => ContactPointType::Website->value,
                'value' => 'https://council.example.gov.ua',
                'is_public' => true,
            ],
        ],
    ])->assertHasNoTableActionErrors();

    $contacts = $deputy->contactPoints()->orderBy('id')->get();

    expect($contacts)->toHaveCount(3)
        ->and($contacts[0]->type)->toBe(ContactPointType::Email)
        ->and($contacts[0]->value)->toBe('deputy@example.gov.ua')
        ->and($contacts[0]->is_public)->toBeTrue()
        ->and($contacts[0]->note)->toBe('Робоча адреса')
        ->and($contacts[1]->type)->toBe(ContactPointType::Phone)
        ->and($contacts[1]->is_public)->toBeFalse()
        ->and($contacts[1]->note)->toBe('Для внутрішнього зв’язку')
        ->and($contacts[2]->type)->toBe(ContactPointType::Website)
        ->and($contacts[2]->value)->toBe('https://council.example.gov.ua')
        ->and($contacts[0]->contactable)->toBeInstanceOf(Deputy::class)
        ->and($contacts[0]->contactable->is($deputy))->toBeTrue();

    $this->assertDatabaseHas('contact_points', [
        'id' => $contacts[0]->id,
        'contactable_id' => $deputy->id,
        'contactable_type' => $deputy->getMorphClass(),
    ]);
});

it('associates contact points with other model types', function () {
    $party = PoliticalParty::factory()->create();

    $contactPoint = ContactPoint::factory()
        ->for($party, 'contactable')
        ->create();

    expect($contactPoint->contactable)->toBeInstanceOf(PoliticalParty::class)
        ->and($contactPoint->contactable->is($party))->toBeTrue();
});

it('validates values according to the selected contact type', function () {
    $deputy = Deputy::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->callTableAction('edit', $deputy, [
            'political_party_id' => $deputy->political_party_id,
            'contactPoints' => [
                [
                    'type' => ContactPointType::Email->value,
                    'value' => 'not-an-email',
                    'is_public' => false,
                ],
            ],
        ])
        ->assertHasTableActionErrors();

    expect($deputy->contactPoints()->count())->toBe(0);
});

it('updates deputy details without changing its linked person or reception', function () {
    $party = PoliticalParty::factory()->create();
    $deputy = Deputy::factory()
        ->has(DeputyReception::factory(), 'reception')
        ->create(['political_party_id' => $party->id]);
    $personId = $deputy->person_id;
    $receptionId = $deputy->reception->id;

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->callTableAction('edit', $deputy, [
            'biography' => 'Оновлена біографія.',
        ])
        ->assertHasNoTableActionErrors();

    $deputy->refresh();

    expect($deputy->person_id)->toBe($personId)
        ->and($deputy->biography)->toBe('Оновлена біографія.')
        ->and($deputy->reception->id)->toBe($receptionId);
});

it('stores the voting identifier and party on each convocation-specific deputy', function () {
    $organization = CouncilOrganization::factory()->create();
    $person = Person::factory()->create();
    $firstConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $secondConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $firstParty = PoliticalParty::factory()->create(['name' => 'Партія перша']);
    $secondParty = PoliticalParty::factory()->create(['name' => 'Партія друга']);

    $firstDeputy = Deputy::factory()
        ->for($person, 'person')
        ->for($firstConvocation, 'convocation')
        ->create([
            'political_party_id' => $firstParty->id,
            'voting_identifier' => 'VOTE-001',
        ]);
    $secondDeputy = Deputy::factory()
        ->for($person, 'person')
        ->for($secondConvocation, 'convocation')
        ->create([
            'political_party_id' => $secondParty->id,
            'voting_identifier' => 'VOTE-002',
        ]);

    expect($person->deputies)->toHaveCount(2)
        ->and($firstDeputy->politicalParty->name)->toBe('Партія перша')
        ->and($secondDeputy->politicalParty->name)->toBe('Партія друга')
        ->and($firstDeputy->voting_identifier)->toBe('VOTE-001')
        ->and($secondDeputy->voting_identifier)->toBe('VOTE-002');
});

it('allows duplicate voting identifiers within one convocation', function () {
    $convocation = Convocation::factory()->create();
    $firstDeputy = Deputy::factory()
        ->for($convocation, 'convocation')
        ->create(['voting_identifier' => 'VOTE-001']);

    $secondDeputy = Deputy::factory()
        ->for($convocation, 'convocation')
        ->create(['voting_identifier' => 'VOTE-001']);

    expect(Deputy::query()
        ->where('convocation_id', $convocation->id)
        ->where('voting_identifier', 'VOTE-001')
        ->get())->toHaveCount(2)
        ->and($firstDeputy->voting_identifier)->toBe($secondDeputy->voting_identifier);
});

it('saves term, faction, and commission-related data on the deputy', function () {
    $convocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create();
    $deputy = Deputy::factory()
        ->for($convocation, 'convocation')
        ->create(['political_party_id' => $party->id]);
    $faction = Faction::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => 'Фракція розвитку']);

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('edit', $deputy, [
        'faction_id' => $faction->id,
        'faction_position' => 'Голова фракції',
        'role' => 'Депутат міської ради',
    ])
        ->assertHasNoTableActionErrors();

    $deputy->refresh();

    expect($deputy->faction->is($faction))->toBeTrue()
        ->and($deputy->faction_position)->toBe('Голова фракції')
        ->and($deputy->convocation_id)->toBe($convocation->id);
});

it('rejects a deputy faction from another convocation', function () {
    $organization = CouncilOrganization::factory()->create();
    $membershipConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $otherConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $faction = Faction::factory()
        ->for($otherConvocation, 'convocation')
        ->create();

    expect(fn () => Deputy::factory()
        ->for($membershipConvocation, 'convocation')
        ->create(['faction_id' => $faction->id]))
        ->toThrow(ValidationException::class);
});

it('keeps term details on the deputy form instead of a membership manager', function () {
    $deputy = Deputy::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $deputy->convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->mountTableAction('edit', $deputy)
        ->assertTableActionDataSet(fn (array $data): bool => array_key_exists('role', $data)
            && array_key_exists('faction_id', $data)
            && array_key_exists('permanent_commission_id', $data));
});

it('removes the separate deputy list and edit pages', function () {
    $convocation = Convocation::factory()->create([
        'label' => 'Сьоме скликання',
    ]);
    $deputy = Deputy::factory()->create([
        'convocation_id' => $convocation->id,
    ]);

    $this->get("/panel/convocations/{$convocation->id}/deputies")->assertNotFound();
    $this->get("/panel/convocations/{$convocation->id}/deputies/create")->assertNotFound();
    $this->get("/panel/convocations/{$convocation->id}/deputies/{$deputy->id}/edit")->assertNotFound();
});

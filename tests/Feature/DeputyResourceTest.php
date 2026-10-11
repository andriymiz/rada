<?php

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Filament\Resources\Deputies\Pages\CreateDeputy;
use App\Filament\Resources\Deputies\Pages\EditDeputy;
use App\Filament\Resources\Deputies\RelationManagers\ContactPointsRelationManager;
use App\Filament\Resources\Deputies\RelationManagers\MembershipsRelationManager;
use App\Models\ContactPoint;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\Deputy;
use App\Models\DeputyReception;
use App\Models\Membership;
use App\Models\PoliticalParty;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a deputy profile without requiring reception details', function () {
    livewire(CreateDeputy::class)
        ->fillForm([
            'family_name' => 'Тестове',
            'first_name' => 'Ім’я',
            'additional_name' => 'ПоБатькові',
            'gender' => Gender::Female->value,
            'birth_date' => '1985-03-12',
            'homepage' => 'https://council.example.gov.ua/deputies/test',
            'biography' => 'Біографія депутата.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $deputy = Deputy::query()->where('family_name', 'Тестове')->firstOrFail();

    $this->assertModelExists($deputy);

    expect($deputy->gender)->toBe(Gender::Female)
        ->and($deputy->birth_date->toDateString())->toBe('1985-03-12')
        ->and($deputy->homepage)->toBe('https://council.example.gov.ua/deputies/test')
        ->and($deputy->full_name)->toBe('Тестове Ім’я ПоБатькові')
        ->and($deputy->reception)->toBeNull()
        ->and($deputy->getAttributes())->not->toHaveKey('name')
        ->and($deputy->getAttributes())->not->toHaveKey('identifier')
        ->and($deputy->getAttributes())->not->toHaveKey('voting_identifier');

    expect($deputy->contactPoints)->toBeEmpty();
});

it('rejects deputy homepage values that are not HTTP URLs', function () {
    livewire(CreateDeputy::class)
        ->fillForm([
            'family_name' => 'Тестове',
            'first_name' => 'Ім’я',
            'additional_name' => 'ПоБатькові',
            'gender' => Gender::Female->value,
            'birth_date' => '1985-03-12',
            'homepage' => 'not-a-url',
            'biography' => 'Біографія депутата.',
        ])
        ->call('create')
        ->assertHasFormErrors(['homepage']);

    expect(Deputy::query()->where('family_name', 'Тестове')->exists())->toBeFalse();
});

it('shows the selected gender label in the deputy profile infolist', function () {
    $deputy = Deputy::factory()->create(['gender' => Gender::Female]);

    livewire(EditDeputy::class, ['record' => $deputy->getKey()])
        ->assertSee('Жінка');
});

it('formats a deputy short name with initials', function () {
    $deputy = Deputy::factory()->create([
        'family_name' => 'Шевченко',
        'first_name' => 'Богдан',
        'additional_name' => 'Вікторович',
    ]);

    expect($deputy->short_name)->toBe('Шевченко Б. В.');
});

it('accepts gender code 9 and displays its label in the deputy profile', function () {
    livewire(CreateDeputy::class)
        ->fillForm([
            'family_name' => 'Тестове',
            'first_name' => 'Ім’я',
            'additional_name' => 'ПоБатькові',
            'gender' => 9,
            'birth_date' => '1985-03-12',
            'biography' => 'Біографія депутата.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $deputy = Deputy::query()->where('family_name', 'Тестове')->firstOrFail();

    expect($deputy->gender)->toBe(Gender::NotApplicable);

    livewire(EditDeputy::class, ['record' => $deputy->getKey()])
        ->assertSee('Не застосовується');
});

it('adds reception details only when requested', function () {
    $deputy = Deputy::factory()->create();

    livewire(EditDeputy::class, ['record' => $deputy->getKey()])
        ->callAction(TestAction::make('addReception')->schemaComponent('reception-add-section'), [
            'katoottg' => 'UA12345678901234567',
            'address_post_code' => '01001',
            'address_admin_unit_l1' => 'Україна',
            'address_admin_unit_l2' => 'Київська область',
            'address_post_name' => 'Тестове',
            'address_thoroughfare' => 'Тестова',
            'address_locator_designator' => '1',
            'opening_hours' => 'Пн 09:00–18:00',
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(DeputyReception::class, [
        'deputy_id' => $deputy->id,
        'katoottg' => 'UA12345678901234567',
        'address_post_name' => 'Тестове',
        'opening_hours' => 'Пн 09:00–18:00',
    ]);
});

it('creates public and private contact points with notes through the relation manager', function () {
    $deputy = Deputy::factory()->create();

    $manager = livewire(ContactPointsRelationManager::class, [
        'ownerRecord' => $deputy,
        'pageClass' => EditDeputy::class,
    ]);

    $manager->callTableAction('create', null, [
        'type' => ContactPointType::Email->value,
        'value' => 'deputy@example.gov.ua',
        'is_public' => true,
        'note' => 'Робоча адреса',
    ])->assertHasNoTableActionErrors();

    $manager->callTableAction('create', null, [
        'type' => ContactPointType::Phone->value,
        'value' => '+380123456789',
        'is_public' => false,
        'note' => 'Для внутрішнього зв’язку',
    ])->assertHasNoTableActionErrors();

    $manager->callTableAction('create', null, [
        'type' => ContactPointType::Website->value,
        'value' => 'https://council.example.gov.ua',
        'is_public' => true,
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

    livewire(ContactPointsRelationManager::class, [
        'ownerRecord' => $deputy,
        'pageClass' => EditDeputy::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData([
            'type' => ContactPointType::Email->value,
            'value' => 'not-an-email',
            'is_public' => false,
        ])
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['value']);

    livewire(ContactPointsRelationManager::class, [
        'ownerRecord' => $deputy,
        'pageClass' => EditDeputy::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData([
            'type' => ContactPointType::Website->value,
            'value' => 'not-a-url',
            'is_public' => false,
        ])
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['value']);

    expect($deputy->contactPoints()->count())->toBe(0);
});

it('updates a deputy and keeps the full name synchronized', function () {
    $deputy = Deputy::factory()
        ->has(DeputyReception::factory(), 'reception')
        ->create();

    livewire(EditDeputy::class, ['record' => $deputy->getKey()])
        ->fillForm(['family_name' => 'Оновлене'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($deputy->refresh()->full_name)
        ->toBe("Оновлене {$deputy->first_name} {$deputy->additional_name}");
});

it('stores the voting identifier and party independently for each convocation', function () {
    $organization = CouncilOrganization::factory()->create();
    $deputy = Deputy::factory()->create();
    $firstConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $secondConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $firstParty = PoliticalParty::factory()->create(['name' => 'Партія перша']);
    $secondParty = PoliticalParty::factory()->create(['name' => 'Партія друга']);

    $firstMembership = Membership::factory()
        ->for($deputy, 'deputy')
        ->for($organization, 'organization')
        ->for($firstConvocation, 'convocation')
        ->create([
            'political_party_id' => $firstParty->id,
            'voting_identifier' => 'VOTE-001',
        ]);
    $secondMembership = Membership::factory()
        ->for($deputy, 'deputy')
        ->for($organization, 'organization')
        ->for($secondConvocation, 'convocation')
        ->create([
            'political_party_id' => $secondParty->id,
            'voting_identifier' => 'VOTE-002',
        ]);

    expect($deputy->memberships)->toHaveCount(2)
        ->and($firstMembership->politicalParty->name)->toBe('Партія перша')
        ->and($secondMembership->politicalParty->name)->toBe('Партія друга')
        ->and($firstMembership->voting_identifier)->toBe('VOTE-001')
        ->and($secondMembership->voting_identifier)->toBe('VOTE-002');
});

it('registers the convocation membership manager on deputy pages', function () {
    $deputy = Deputy::factory()->create();

    $page = livewire(EditDeputy::class, ['record' => $deputy->getKey()])->assertOk();

    expect($page->instance()->getRelationManagers())
        ->toContain(ContactPointsRelationManager::class)
        ->toContain(MembershipsRelationManager::class);
});

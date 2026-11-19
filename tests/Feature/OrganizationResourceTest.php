<?php

use App\Filament\Exports\OrganizationExporter;
use App\Filament\Imports\OrganizationImporter;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\OfficialsRelationManager;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationGroup;
use App\Models\Person;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists organizations and creates and edits their profile details', function (): void {
    $group = OrganizationGroup::factory()->create(['name' => 'Місцеві ради']);
    $organization = Organization::factory()->create([
        'full_name' => 'Повна назва ради',
        'short_name' => 'Міська рада',
        'group_id' => $group->getKey(),
    ]);

    livewire(ListOrganizations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$organization])
        ->assertSee('Організації')
        ->assertActionExists('importOrganizations')
        ->assertActionExists('exportOrganizations')
        ->assertTableColumnStateSet('full_name', 'Міська рада', $organization);

    livewire(CreateOrganization::class)
        ->fillForm([
            'edrpou' => '12345678',
            'full_name' => 'Повна назва за ЄДР',
            'short_name' => 'Коротка назва',
            'group_id' => $group->getKey(),
            'description' => 'Опис організації',
            'constituent_doc' => 'https://example.com/statut',
            'homepage' => 'https://example.com',
            'social_accounts' => [['url' => 'https://social.example.com/org']],
            'contact_point' => 'Приймальня',
            'email' => 'rada@example.com',
            'phone' => '+380501234567',
            'additional_contacts' => [['name' => 'Приймальня', 'value' => 'кабінет 12']],
            'opening_hours' => 'Пн 09:00-18:00',
            'availability_restriction' => 'За записом',
            'logo' => 'https://example.com/logo.png',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $createdOrganization = Organization::query()->where('edrpou', '12345678')->firstOrFail();

    $this->assertModelExists($createdOrganization);

    livewire(EditOrganization::class, ['record' => $createdOrganization->getKey()])
        ->fillForm([
            'full_name' => 'Оновлена повна назва',
            'short_name' => 'Оновлена коротка назва',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($createdOrganization->refresh()->full_name)->toBe('Оновлена повна назва')
        ->and($createdOrganization->short_name)->toBe('Оновлена коротка назва')
        ->and($createdOrganization->group->is($group))->toBeTrue()
        ->and($createdOrganization->social_accounts)->toBe(['https://social.example.com/org'])
        ->and($createdOrganization->contact_point)->toBe('Приймальня')
        ->and($createdOrganization->email)->toBe('rada@example.com')
        ->and($createdOrganization->phone)->toBe('+380501234567')
        ->and($createdOrganization->additional_contacts)->toBe(['кабінет 12'])
        ->and($createdOrganization->opening_hours)->toBe('Пн 09:00-18:00')
        ->and($createdOrganization->availability_restriction)->toBe('За записом');

    $this->assertDatabaseHas(Organization::class, [
        'id' => $createdOrganization->getKey(),
        'full_name' => 'Оновлена повна назва',
    ]);
});

it('stores the parent organization relationship', function (): void {
    $parent = Organization::factory()->create();
    $organization = Organization::factory()->create();

    livewire(EditOrganization::class, ['record' => $organization->getKey()])
        ->fillForm(['parent_org_id' => $parent->getKey(), 'description' => 'Опис', 'email' => 'office@example.com', 'phone' => '+380501234567'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->refresh()->parentOrganization->is($parent))->toBeTrue()
        ->and($parent->refresh()->childOrganizations->contains($organization))->toBeTrue();
});

it('validates singular contacts in the organization form', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm([
            'edrpou' => '12345678',
            'full_name' => 'Організація',
            'short_name' => 'Організація',
            'email' => 'not-an-email',
            'phone' => '123,456',
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'phone']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('requires email and telephone in the organization form', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm(['edrpou' => '12345678', 'full_name' => 'Організація', 'short_name' => 'Організація', 'email' => '', 'phone' => ''])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required', 'phone' => 'required']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('requires primary contacts when importing an organization', function (): void {
    OrganizationImporter::test()
        ->import([
            'edrpou' => '12345678',
            'name' => 'Організація',
            'description' => 'Опис',
            'email' => 'null',
            'phone' => '',
        ])
        ->assertHasErrors(['email' => 'required', 'phone' => 'required']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('selects an organization head from its associated officials', function (): void {
    $organization = Organization::factory()->create();
    $head = Employee::factory()
        ->for($organization, 'organization')
        ->create(['is_post' => true]);
    $candidate = Employee::factory()
        ->for($organization, 'organization')
        ->create(['is_post' => false]);
    $unrelatedOfficial = Employee::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $organization,
        'pageClass' => EditOrganization::class,
    ])
        ->assertCanSeeTableRecords([$head, $candidate])
        ->assertCanNotSeeTableRecords([$unrelatedOfficial])
        ->assertTableColumnStateSet('is_post', true, $head);

    livewire(EditOrganization::class, ['record' => $organization->getKey()])
        ->fillForm([
            'head_id' => $candidate->getKey(),
            'description' => 'Опис організації',
            'email' => 'office@example.com',
            'phone' => '+380501234567',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->refresh()->head->is($candidate))->toBeTrue()
        ->and($head->refresh()->is_post)->toBeTrue()
        ->and($candidate->refresh()->is_post)->toBeFalse();
});

it('rejects organization heads that belong to a different organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOfficial = Employee::factory()->create();

    livewire(EditOrganization::class, ['record' => $organization->getKey()])
        ->fillForm([
            'head_id' => $otherOfficial->getKey(),
            'description' => 'Опис організації',
            'email' => 'office@example.com',
            'phone' => '+380501234567',
        ])
        ->call('save')
        ->assertHasFormErrors(['head_id']);

    expect($organization->refresh()->head_id)->toBeNull();
});

it('creates officials from the organization edit page relation manager', function (): void {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $organization,
        'pageClass' => EditOrganization::class,
    ])
        ->callTableAction('create', data: [
            'person_id' => $person->getKey(),
            'position' => 'Секретар',
            'email' => 'official@example.com',
            'phone' => '+380501234567',
        ]);

    $official = Employee::query()->where('person_id', $person->getKey())->firstOrFail();

    expect($official->organization->is($organization))->toBeTrue();

    $this->get(OrganizationResource::getUrl('edit', ['record' => $organization]))
        ->assertOk()
        ->assertSeeLivewire(OfficialsRelationManager::class);
});

it('saves official contact details without requiring an address', function (): void {
    $official = Employee::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])
        ->callTableAction('edit', $official, [
            'email' => 'official@example.com',
            'phone' => '+380501234567',
            'is_post' => true,
            'additional_contacts' => [['name' => 'Помічник', 'value' => 'assistant@example.com']],
            'social_accounts' => [['url' => 'https://facebook.com/official']],
            'opening_hours' => 'Пн 09:00-12:00',
            'availability_restriction' => 'За записом',
        ])
        ->assertHasNoTableActionErrors();

    $official->refresh();
    expect($official->email)->toBe('official@example.com')
        ->and($official->phone)->toBe('+380501234567')
        ->and($official->is_post)->toBeTrue()
        ->and($official->additional_contacts)->toBe(['assistant@example.com'])
        ->and($official->social_accounts)->toBe(['https://facebook.com/official'])
        ->and($official->opening_hours)->toBe('Пн 09:00-12:00')
        ->and($official->availability_restriction)->toBe('За записом')
        ->and($official->reception)->toBeNull();
});

it('validates each contact in the official form', function (): void {
    $official = Employee::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])->callTableAction('edit', $official, [
        'email' => 'not-an-email',
        'phone' => '123,456',
    ])->assertHasTableActionErrors(['email', 'phone']);

    expect($official->refresh()->email)->toBeNull();
});

it('requires email and telephone in the official form', function (): void {
    $official = Employee::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])->callTableAction('edit', $official, [
        'email' => '',
        'phone' => '',
    ])->assertHasTableActionErrors(['email' => 'required', 'phone' => 'required']);

    expect($official->refresh()->email)->toBeNull();
});

it('stores organization heads separately from official post flags', function (): void {
    expect(Schema::hasTable('employees'))->toBeTrue()
        ->and(Schema::hasTable('officials'))->toBeFalse()
        ->and(Schema::hasColumn('employees', 'is_post'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'is_head'))->toBeFalse()
        ->and(Schema::hasColumn('organizations', 'head_id'))->toBeTrue();
});

it('stores singular and additional contacts for every contactable entity', function (): void {
    foreach (['organizations', 'organization_units', 'employees', 'deputies'] as $table) {
        expect(Schema::hasColumns($table, ['email', 'phone', 'additional_contacts']))->toBeTrue()
            ->and(Schema::hasColumn($table, 'emails'))->toBeFalse()
            ->and(Schema::hasColumn($table, 'phones'))->toBeFalse();
    }

    expect(Schema::hasColumns('organizations', ['full_name', 'group_id']))->toBeTrue()
        ->and(Schema::hasColumn('organizations', 'name'))->toBeFalse();
});

it('imports organizations and resolves their official head and parent', function (): void {
    $parent = Organization::factory()->create([
        'edrpou' => '11112222',
        'full_name' => 'Батьківська організація',
    ]);

    OrganizationImporter::test(columnMap: [
        'edrpou' => 'Id',
        'full_name' => 'name',
        'short_name' => 'shortName',
        'description' => 'description',
        'constituent_doc' => 'constituentDocUrl',
        'homepage' => 'homepage',
        'social_accounts' => 'socialAccount',
        'contact_point' => 'contactPointName',
        'email' => 'contactPointEmail',
        'phone' => 'contactPointTelephone',
        'opening_hours' => 'openingHours',
        'availability_restriction' => 'availabilityRestriction',
        'logo' => 'logo',
        'headName' => 'headName',
        'headPost' => 'headPost',
        'parentOrganization' => 'subOrgOfId',
        'parentOrganizationName' => 'subOrgOfName',
    ])
        ->import([
            'Id' => '33334444',
            'name' => 'Повна назва установи',
            'shortName' => 'Установа',
            'description' => 'Опис установи',
            'constituentDocUrl' => 'null',
            'homepage' => 'https://example.com',
            'socialAccount' => 'https://social.example.com/one, https://social.example.com/two',
            'contactPointName' => 'Приймальня',
            'contactPointEmail' => 'rada@example.com',
            'contactPointTelephone' => '380501234567',
            'openingHours' => 'Пн 09:00-18:00',
            'availabilityRestriction' => 'За записом',
            'logo' => 'null',
            'headName' => 'Коваль Олена Вікторівна',
            'headPost' => 'Директор',
            'subOrgOfId' => '11112222',
            'subOrgOfName' => 'Батьківська організація',
        ])
        ->assertImported();

    $organization = Organization::query()->where('edrpou', '33334444')->firstOrFail();

    expect($organization->full_name)->toBe('Повна назва установи')
        ->and($organization->constituent_doc)->toBeNull()
        ->and($organization->social_accounts)->toBe([
            'https://social.example.com/one',
            'https://social.example.com/two',
        ])
        ->and($organization->contact_point)->toBe('Приймальня')
        ->and($organization->email)->toBe('rada@example.com')
        ->and($organization->phone)->toBe('+380501234567')
        ->and($organization->opening_hours)->toBe('Пн 09:00-18:00')
        ->and($organization->availability_restriction)->toBe('За записом')
        ->and($organization->parentOrganization->is($parent))->toBeTrue()
        ->and($organization->head->position)->toBe('Директор')
        ->and($organization->head->is_post)->toBeTrue()
        ->and($organization->head->person->full_name)->toBe('Коваль Олена Вікторівна');
});

it('exports organization rows using the structure csv columns', function (): void {
    $parent = Organization::factory()->create([
        'edrpou' => '11112222',
        'full_name' => 'Батьківська організація',
    ]);
    $organization = Organization::factory()->create([
        'edrpou' => '33334444',
        'full_name' => 'Повна назва установи',
        'short_name' => 'Установа',
        'description' => 'Опис установи',
        'constituent_doc' => 'https://example.com/statut',
        'homepage' => 'https://example.com',
        'social_accounts' => ['https://social.example.com/org'],
        'contact_point' => 'Приймальня',
        'email' => 'rada@example.com',
        'phone' => '+380501234567',
        'opening_hours' => 'Пн 09:00-18:00',
        'availability_restriction' => 'За записом',
        'logo' => 'https://example.com/logo.png',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Коваль',
        'first_name' => 'Олена',
        'middle_name' => 'Вікторівна',
    ]);
    $head = Employee::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create([
            'position' => 'Директор',
            'is_post' => true,
        ]);
    $organization->head()->associate($head);
    $organization->parentOrganization()->associate($parent);
    $organization->save();
    $organization->load(['head.person', 'parentOrganization']);

    expect(OrganizationExporter::test()->export($organization))->toBe([
        '33334444',
        'Повна назва установи',
        'Опис установи',
        'https://example.com/statut',
        'https://example.com',
        'https://social.example.com/org',
        'https://example.com/logo.png',
        'Коваль Олена Вікторівна',
        'Директор',
        '11112222',
        'Батьківська організація',
        'null', 'null', 'null', 'null', 'null',
        'null', 'null', 'null', 'null', 'null',
        'Приймальня',
        'rada@example.com',
        '+380501234567',
        'Пн 09:00-18:00',
        'За записом',
    ]);
});

it('does not allow non-admin users to access the organizations resource', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(ListOrganizations::class)->assertForbidden();

    $this->get(OrganizationResource::getUrl())->assertForbidden();
});

<?php

use App\Filament\Exports\OrganizationExporter;
use App\Filament\Imports\OrganizationImporter;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\OfficialsRelationManager;
use App\Models\Official;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists organizations and creates and edits their profile details', function (): void {
    $organization = Organization::factory()->create([
        'name' => 'Повна назва ради',
        'short_name' => 'Міська рада',
    ]);

    livewire(ListOrganizations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$organization])
        ->assertSee('Організації')
        ->assertActionExists('importOrganizations')
        ->assertActionExists('exportOrganizations')
        ->assertTableColumnStateSet('name', 'Міська рада', $organization);

    livewire(CreateOrganization::class)
        ->fillForm([
            'edrpou' => '12345678',
            'name' => 'Повна назва за ЄДР',
            'short_name' => 'Коротка назва',
            'description' => 'Опис організації',
            'constituent_doc' => 'https://example.com/statut',
            'homepage' => 'https://example.com',
            'social_accounts' => [['url' => 'https://social.example.com/org']],
            'contact_point' => 'Приймальня',
            'emails' => [['email' => 'rada@example.com'], ['email' => 'office@example.com'], ['email' => 'info@example.com']],
            'phones' => [['phone' => '+380501234567'], ['phone' => '+380501234568'], ['phone' => '+380501234569']],
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
            'name' => 'Оновлена повна назва',
            'short_name' => 'Оновлена коротка назва',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($createdOrganization->refresh()->name)->toBe('Оновлена повна назва')
        ->and($createdOrganization->short_name)->toBe('Оновлена коротка назва')
        ->and($createdOrganization->social_accounts)->toBe(['https://social.example.com/org'])
        ->and($createdOrganization->contact_point)->toBe('Приймальня')
        ->and($createdOrganization->emails)->toBe(['rada@example.com', 'office@example.com', 'info@example.com'])
        ->and($createdOrganization->phones)->toBe(['+380501234567', '+380501234568', '+380501234569'])
        ->and($createdOrganization->opening_hours)->toBe('Пн 09:00-18:00')
        ->and($createdOrganization->availability_restriction)->toBe('За записом');

    $this->assertDatabaseHas(Organization::class, [
        'id' => $createdOrganization->getKey(),
        'name' => 'Оновлена повна назва',
    ]);
});

it('stores the parent organization relationship', function (): void {
    $parent = Organization::factory()->create();
    $organization = Organization::factory()->create();

    livewire(EditOrganization::class, ['record' => $organization->getKey()])
        ->fillForm(['parent_org_id' => $parent->getKey(), 'description' => 'Опис', 'emails' => [['email' => 'office@example.com']], 'phones' => [['phone' => '+380501234567']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->refresh()->parentOrganization->is($parent))->toBeTrue()
        ->and($parent->refresh()->childOrganizations->contains($organization))->toBeTrue();
});

it('validates each contact in the organization form', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm([
            'edrpou' => '12345678',
            'name' => 'Організація',
            'short_name' => 'Організація',
            'emails' => [['email' => 'not-an-email']],
            'phones' => [['phone' => '123,456']],
        ])
        ->call('create')
        ->assertHasFormErrors(['emails.0.email', 'phones.0.phone']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('requires email and telephone lists in the organization form', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm(['edrpou' => '12345678', 'name' => 'Організація', 'short_name' => 'Організація', 'emails' => [], 'phones' => []])
        ->call('create')
        ->assertHasFormErrors(['emails' => 'required', 'phones' => 'required']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('requires primary contacts when importing an organization', function (): void {
    OrganizationImporter::test()
        ->import([
            'edrpou' => '12345678',
            'name' => 'Організація',
            'description' => 'Опис',
            'emails' => 'null',
            'phones' => '',
        ])
        ->assertHasErrors(['emails' => 'required', 'phones' => 'required']);

    expect(Organization::query()->where('edrpou', '12345678')->exists())->toBeFalse();
});

it('manages officials through the organization relation manager and assigns a single head', function (): void {
    $organization = Organization::factory()->create();
    $head = Official::factory()
        ->for($organization, 'organization')
        ->create(['is_head' => true]);
    $candidate = Official::factory()
        ->for($organization, 'organization')
        ->create(['is_head' => false]);
    $unrelatedOfficial = Official::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $organization,
        'pageClass' => EditOrganization::class,
    ])
        ->assertCanSeeTableRecords([$head, $candidate])
        ->assertCanNotSeeTableRecords([$unrelatedOfficial])
        ->assertTableColumnStateSet('is_head', true, $head)
        ->callAction(TestAction::make('markAsHead')->table($candidate));

    expect($organization->fresh()->head->is($candidate))->toBeTrue()
        ->and($head->refresh()->is_head)->toBeFalse()
        ->and($candidate->refresh()->is_head)->toBeTrue()
        ->and($organization->officials()->where('is_head', true)->count())->toBe(1);
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
            'emails' => [['email' => 'official@example.com'], ['email' => 'assistant@example.com']],
            'phones' => [['phone' => '+380501234567'], ['phone' => '+380501234568']],
        ])
        ->assertHasNoTableActionErrors();

    $official = Official::query()->where('person_id', $person->getKey())->firstOrFail();

    expect($official->organization->is($organization))->toBeTrue();

    $this->get(OrganizationResource::getUrl('edit', ['record' => $organization]))
        ->assertOk()
        ->assertSeeLivewire(OfficialsRelationManager::class);
});

it('saves official contact details without requiring an address', function (): void {
    $official = Official::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])
        ->callTableAction('edit', $official, [
            'emails' => [['email' => 'official@example.com'], ['email' => 'assistant@example.com']],
            'phones' => [['phone' => '+380501234567'], ['phone' => '+380501234568']],
            'social_accounts' => [['url' => 'https://facebook.com/official']],
            'opening_hours' => 'Пн 09:00-12:00',
            'availability_restriction' => 'За записом',
        ])
        ->assertHasNoTableActionErrors();

    $official->refresh();
    expect($official->emails)->toBe(['official@example.com', 'assistant@example.com'])
        ->and($official->phones)->toBe(['+380501234567', '+380501234568'])
        ->and($official->social_accounts)->toBe(['https://facebook.com/official'])
        ->and($official->opening_hours)->toBe('Пн 09:00-12:00')
        ->and($official->availability_restriction)->toBe('За записом')
        ->and($official->reception)->toBeNull();
});

it('validates each contact in the official form', function (): void {
    $official = Official::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])->callTableAction('edit', $official, [
        'emails' => [['email' => 'not-an-email']],
        'phones' => [['phone' => '123,456']],
    ])->assertHasTableActionErrors(['emails.0.email', 'phones.0.phone']);

    expect($official->refresh()->emails)->toBeNull();
});

it('requires email and telephone lists in the official form', function (): void {
    $official = Official::factory()->create();

    livewire(OfficialsRelationManager::class, [
        'ownerRecord' => $official->organization,
        'pageClass' => EditOrganization::class,
    ])->callTableAction('edit', $official, [
        'emails' => [],
        'phones' => [],
    ])->assertHasTableActionErrors(['emails' => 'required', 'phones' => 'required']);

    expect($official->refresh()->emails)->toBeNull();
});

it('stores the head flag on officials instead of an organization foreign key', function (): void {
    expect(Schema::hasColumn('officials', 'is_head'))->toBeTrue()
        ->and(Schema::hasColumn('organizations', 'head_id'))->toBeFalse();
});

it('imports organizations and resolves their official head and parent', function (): void {
    $parent = Organization::factory()->create([
        'edrpou' => '11112222',
        'name' => 'Батьківська організація',
    ]);

    OrganizationImporter::test(columnMap: [
        'edrpou' => 'Id',
        'name' => 'name',
        'short_name' => 'shortName',
        'description' => 'description',
        'constituent_doc' => 'constituentDocUrl',
        'homepage' => 'homepage',
        'social_accounts' => 'socialAccount',
        'contact_point' => 'contactPointName',
        'emails' => 'contactPointEmail',
        'phones' => 'contactPointTelephone',
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
            'contactPointEmail' => 'rada@example.com, office@example.com',
            'contactPointTelephone' => '380501234567, +380501234568',
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

    expect($organization->name)->toBe('Повна назва установи')
        ->and($organization->constituent_doc)->toBeNull()
        ->and($organization->social_accounts)->toBe([
            'https://social.example.com/one',
            'https://social.example.com/two',
        ])
        ->and($organization->contact_point)->toBe('Приймальня')
        ->and($organization->emails)->toBe(['rada@example.com', 'office@example.com'])
        ->and($organization->phones)->toBe(['+380501234567', '+380501234568'])
        ->and($organization->opening_hours)->toBe('Пн 09:00-18:00')
        ->and($organization->availability_restriction)->toBe('За записом')
        ->and($organization->parentOrganization->is($parent))->toBeTrue()
        ->and($organization->head->position)->toBe('Директор')
        ->and($organization->head->is_head)->toBeTrue()
        ->and($organization->head->person->full_name)->toBe('Коваль Олена Вікторівна');
});

it('exports organization rows using the structure csv columns', function (): void {
    $parent = Organization::factory()->create([
        'edrpou' => '11112222',
        'name' => 'Батьківська організація',
    ]);
    $organization = Organization::factory()->create([
        'edrpou' => '33334444',
        'name' => 'Повна назва установи',
        'short_name' => 'Установа',
        'description' => 'Опис установи',
        'constituent_doc' => 'https://example.com/statut',
        'homepage' => 'https://example.com',
        'social_accounts' => ['https://social.example.com/org'],
        'contact_point' => 'Приймальня',
        'emails' => ['rada@example.com'],
        'phones' => ['+380501234567'],
        'opening_hours' => 'Пн 09:00-18:00',
        'availability_restriction' => 'За записом',
        'logo' => 'https://example.com/logo.png',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Коваль',
        'first_name' => 'Олена',
        'middle_name' => 'Вікторівна',
    ]);
    $head = Official::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create([
            'position' => 'Директор',
            'is_head' => true,
        ]);
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

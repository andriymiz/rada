<?php

use App\Filament\Exports\OrganizationUnitExporter;
use App\Filament\Imports\OrganizationUnitImporter;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\OrganizationUnitsRelationManager;
use App\Models\Official;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

function organizationUnitImportMap(): array
{
    return [
        'uid' => 'uid',
        'full_name' => 'name',
        'description' => 'description',
        'constituent_doc' => 'constituentDocUrl',
        'homepage' => 'homepage',
        'headName' => 'headName',
        'headPost' => 'headPost',
        'organization' => 'unitOfId',
        'parentUnit' => 'subUnitOfId',
        'subUnitOfName' => 'subUnitOfName',
        'contact_point' => 'contactPointName',
        'emails' => 'contactPointEmail',
        'phones' => 'contactPointTelephone',
        'opening_hours' => 'openingHours',
        'availability_restriction' => 'availabilityRestriction',
    ];
}

function organizationUnitImportRow(array $overrides = []): array
{
    return array_replace([
        'uid' => '1.2',
        'name' => 'Архівний відділ',
        'description' => 'Зберігання документів',
        'constituentDocUrl' => 'null',
        'homepage' => 'https://example.com/archive',
        'headName' => 'Коваль Олена',
        'headPost' => 'Начальник',
        'unitOfId' => '12345678',
        'unitOfName' => 'Міська рада',
        'subUnitOfId' => 'null',
        'subUnitOfName' => 'null',
        'contactPointName' => 'Приймальня',
        'contactPointEmail' => 'first@example.com, second@example.com',
        'contactPointTelephone' => '380501234567, +380501234568',
        'openingHours' => 'Пн 09:00-17:00',
        'availabilityRestriction' => 'За записом',
    ], $overrides);
}

it('manages organization units within their organization', function (): void {
    $organization = Organization::factory()->create();
    $head = Official::factory()->for($organization, 'organization')->create();
    $parent = OrganizationUnit::factory()->for($organization, 'organization')->create();
    $other = OrganizationUnit::factory()->create();

    livewire(OrganizationUnitsRelationManager::class, [
        'ownerRecord' => $organization,
        'pageClass' => EditOrganization::class,
    ])
        ->assertCanSeeTableRecords([$parent])
        ->assertCanNotSeeTableRecords([$other])
        ->callTableAction('create', data: [
            'uid' => '1.2',
            'full_name' => 'Архівний відділ',
            'short_name' => 'Архів',
            'description' => 'Зберігання документів',
            'head_id' => $head->id,
            'parent_unit_id' => $parent->id,
            'contact_point' => 'Приймальня',
            'emails' => [['email' => 'office@example.com']],
            'phones' => [['phone' => '+380501234567']],
        ])
        ->assertHasNoTableActionErrors();

    $unit = $organization->organizationUnits()->where('uid', '1.2')->sole();
    expect($unit->head->is($head))->toBeTrue()
        ->and($unit->parentUnit->is($parent))->toBeTrue()
        ->and($unit->full_name)->toBe('Архівний відділ')
        ->and($unit->short_name)->toBe('Архів')
        ->and($unit->emails)->toBe(['office@example.com']);

    livewire(OrganizationUnitsRelationManager::class, [
        'ownerRecord' => $organization,
        'pageClass' => EditOrganization::class,
    ])
        ->assertTableColumnStateSet('full_name', 'Архівний відділ', $unit)
        ->assertTableColumnStateSet('parentUnit.full_name', $parent->full_name, $unit)
        ->searchTable('Архівний відділ')
        ->assertCanSeeTableRecords([$unit])
        ->callTableAction('edit', $unit, ['full_name' => 'Новий відділ'])
        ->assertHasNoTableActionErrors();

    expect($unit->refresh()->full_name)->toBe('Новий відділ');
    $this->get(EditOrganization::getUrl(['record' => $organization]))->assertOk()->assertSee('Підрозділи');
});

it('validates contacts and organizations while importing existing identifiers and parent links', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    $head = Official::factory()->for($organization, 'organization')->for($person)->create(['position' => 'Начальник']);
    $unit = OrganizationUnit::factory()->for($organization, 'organization')->create(['uid' => '1.2', 'head_id' => $head->id]);
    $child = OrganizationUnit::factory()->for($organization, 'organization')->create(['uid' => '1.3', 'parent_unit_id' => $unit->id]);
    $other = OrganizationUnit::factory()->create(['uid' => 'other']);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['contactPointEmail' => ', office@example.com', 'contactPointTelephone' => '']))
        ->assertHasErrors(['emails.0', 'phones']);
    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['unitOfId' => '87654321']))
        ->assertHasErrors(['organization']);
    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['unitOfId' => '']))
        ->assertHasErrors(['organization']);
    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['uid' => $other->uid]))
        ->assertImported();
    expect($other->refresh()->organization_id)->toBe($organization->id);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['subUnitOfId' => $child->uid]))
        ->assertImported();
    expect($unit->refresh()->parent_unit_id)->toBe($child->id);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['subUnitOfId' => $unit->uid]))
        ->assertImported();

    expect($unit->refresh()->parent_unit_id)->toBe($unit->id);
});

it('imports an organization by a mapped code without its name and resolves the parent in that organization', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $parent = OrganizationUnit::factory()->for($organization, 'organization')->create(['uid' => '1']);
    $map = organizationUnitImportMap();
    $map['organization'] = 'organizationCode';
    $row = organizationUnitImportRow(['organizationCode' => '12345678', 'subUnitOfId' => '1']);
    unset($row['unitOfId'], $row['unitOfName']);

    OrganizationUnitImporter::test(columnMap: $map)
        ->import($row)
        ->assertImported();

    $unit = OrganizationUnit::query()->where('uid', '1.2')->sole();
    expect($unit->organization->is($organization))->toBeTrue()
        ->and($unit->parentUnit->is($parent))->toBeTrue();
});

it('imports and updates a unit using existing organization, head and parent', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    $head = Official::factory()->for($organization, 'organization')->for($person)->create(['position' => 'Начальник']);
    $parent = OrganizationUnit::factory()->for($organization, 'organization')->create(['uid' => '1', 'full_name' => 'Управління']);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['subUnitOfId' => '1']))
        ->assertImported();

    $unit = OrganizationUnit::query()->where('uid', '1.2')->sole();
    expect($unit->organization->is($organization))->toBeTrue()
        ->and($unit->head->is($head))->toBeTrue()
        ->and($unit->parentUnit->is($parent))->toBeTrue()
        ->and($unit->full_name)->toBe('Архівний відділ')
        ->and($unit->emails)->toBe(['first@example.com', 'second@example.com'])
        ->and($unit->phones)->toBe(['+380501234567', '+380501234568']);

    $exported = OrganizationUnitExporter::test()->export($unit);
    expect($exported[1])->toBe('Архівний відділ')
        ->and($exported[10])->toBe('Управління');

    $mapWithoutParent = organizationUnitImportMap();
    unset($mapWithoutParent['parentUnit']);
    OrganizationUnitImporter::test(columnMap: $mapWithoutParent)
        ->import(organizationUnitImportRow())
        ->assertImported();
    expect($unit->refresh()->parentUnit->is($parent))->toBeTrue();

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['name' => 'Оновлений відділ', 'subUnitOfId' => 'null']))
        ->assertImported();

    expect($unit->refresh()->full_name)->toBe('Оновлений відділ')
        ->and($unit->parent_unit_id)->toBeNull()
        ->and(OrganizationUnit::query()->where('uid', '1.2')->count())->toBe(1);
});

it('rejects a missing parent and creates a missing head for a valid unit', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    Official::factory()->for($organization, 'organization')->for($person)->create(['position' => 'Начальник']);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['subUnitOfId' => 'missing']))
        ->assertHasErrors(['parentUnit']);

    $otherParent = OrganizationUnit::factory()->create(['uid' => 'other-parent']);
    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['subUnitOfId' => $otherParent->uid]))
        ->assertHasErrors(['parentUnit']);

    expect(OrganizationUnit::query()->where('uid', '1.2')->exists())->toBeFalse();

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['headName' => 'Інша Особа']))
        ->assertImported();

    $unit = OrganizationUnit::query()->where('uid', '1.2')->sole();
    expect($unit->head->person->full_name)->toBe('Інша Особа')
        ->and($unit->head->position)->toBe('Начальник')
        ->and($unit->head->organization->is($organization))->toBeTrue();
});

it('matches a head by name within the organization and updates the position on reimport', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $otherOrganization = Organization::factory()->create();
    $name = ['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null];
    $otherPerson = Person::factory()->create($name);
    $person = Person::factory()->create($name);
    $otherOfficial = Official::factory()->for($otherOrganization, 'organization')->for($otherPerson)->create(['position' => 'Інша посада']);
    $head = Official::factory()->for($organization, 'organization')->for($person)->create(['position' => 'Стара посада']);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow())
        ->assertImported();
    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow(['headPost' => 'Директор']))
        ->assertImported();

    $unit = OrganizationUnit::query()->where('uid', '1.2')->sole();
    expect($unit->head->is($head))->toBeTrue()
        ->and($head->refresh()->position)->toBe('Директор')
        ->and($otherOfficial->refresh()->position)->toBe('Інша посада')
        ->and($organization->officials()->count())->toBe(1)
        ->and(Person::query()->where($name)->count())->toBe(2);
});

it('reuses an existing person when creating a head for the organization', function (): void {
    $organization = Organization::factory()->create(['edrpou' => '12345678']);
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())
        ->import(organizationUnitImportRow())
        ->assertImported();

    $head = OrganizationUnit::query()->where('uid', '1.2')->sole()->head;
    expect($head->person->is($person))->toBeTrue()
        ->and($head->organization->is($organization))->toBeTrue()
        ->and($head->position)->toBe('Начальник');
});

it('exports units from all organizations with the specified CSV columns and imports the exported contacts', function (): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    $organization = Organization::factory()->create(['edrpou' => '12345678', 'name' => 'Міська рада']);
    $person = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    $head = Official::factory()->for($organization, 'organization')->for($person)->create(['position' => 'Начальник']);
    OrganizationUnit::factory()->for($organization, 'organization')->create([
        'uid' => '1.2', 'head_id' => $head->id, 'full_name' => 'Архівний відділ',
        'emails' => ['first@example.com', 'second@example.com'],
        'phones' => ['+380501234567', '+380501234568'],
    ]);
    OrganizationUnit::factory()->create();

    livewire(ListOrganizations::class)
        ->assertActionExists('importOrganizationUnits')
        ->assertActionExists('exportOrganizationUnits', fn (ExportAction $action): bool => $action->getExporter() === OrganizationUnitExporter::class
            && $action->getFileName(new Export) === 'organizationUnits'
            && $action->getFormats() === [ExportFormat::Csv]
            && ! $action->hasColumnMapping())
        ->searchTable('Міська рада')
        ->callAction('exportOrganizationUnits')
        ->assertNotified();

    $export = Export::query()->sole();
    expect($export->total_rows)->toBe(2)->and($export->successful_rows)->toBe(2);
    $csv = Reader::fromString(implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export))));
    $csv->setHeaderOffset(0);
    expect($csv->getHeader())->toBe([
        'uid', 'name', 'description', 'constituentDocUrl', 'homepage', 'headName', 'headPost',
        'unitOfId', 'unitOfName', 'subUnitOfId', 'subUnitOfName', 'CATUTTC',
        'addressAdminUnitL1', 'addressAdminUnitL2', 'addressAdminUnitL3', 'addressAdminUnitL4',
        'addressPostName', 'addressThoroughfare', 'addressLocatorDesignator', 'addressLocatorName',
        'addressPostCode', 'contactPointName', 'contactPointEmail', 'contactPointTelephone',
        'openingHours', 'availabilityRestriction',
    ]);
    $rows = collect(iterator_to_array($csv->getRecords()));
    $row = $rows->firstWhere('uid', '1.2');
    expect($row['unitOfId'])->toBe('12345678')
        ->and($row['name'])->toBe('Архівний відділ')
        ->and($row['headName'])->toBe('Коваль Олена')
        ->and($row['contactPointTelephone'])->toBe("'+380501234567, +380501234568")
        ->and($row['CATUTTC'])->toBe('null');

    OrganizationUnitImporter::test(columnMap: organizationUnitImportMap())->import($row)->assertImported();
    expect(OrganizationUnit::query()->where('uid', '1.2')->sole()->phones)->toBe(['+380501234567', '+380501234568']);
});

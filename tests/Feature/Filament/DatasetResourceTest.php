<?php

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use App\Models\DatasetResource as DatasetResourceModel;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('sets the portal identifier from the search action without fetching metadata', function (): void {
    $packageId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001258';
    $dataset = Dataset::factory()->create([
        'external_id' => null,
    ]);

    Http::preventStrayRequests();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callAction(
            TestAction::make('searchPortalDataset')->schemaComponent('external_id'),
            ['package_id' => $packageId],
        )
        ->assertSet('data.external_id', $packageId)
        ->assertSchemaComponentHidden('portalMetadata');

    Http::assertNothingSent();

    $this->assertDatabaseMissing('dataset_metas', [
        'dataset_id' => $dataset->getKey(),
    ]);
    $this->assertDatabaseHas('datasets', [
        'id' => $dataset->getKey(),
        'external_id' => null,
    ]);
});

it('hides dataset creation until an organization portal ID is configured', function (): void {
    config(['services.open_data.organization_id' => null]);

    livewire(ManageDatasets::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('allows deleting only datasets without a recommendation for each role', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    $recommendedDataset = Dataset::factory()->make(['rec_code' => DatasetRecommendation::LocalDeputies]);
    $customDataset = Dataset::factory()->make(['rec_code' => null]);

    expect(Gate::forUser($user)->allows('delete', $recommendedDataset))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $customDataset))->toBeTrue();
})->with([UserRole::Admin, UserRole::Employee]);

it('hides and rejects deletion of recommended datasets in the table and edit page', function (): void {
    $dataset = Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);

    livewire(ManageDatasets::class)
        ->assertTableActionHidden('delete', $dataset)
        ->mountAction(TestAction::make('delete')->table($dataset))
        ->assertActionNotMounted()
        ->callMountedAction();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertActionHidden('delete')
        ->mountAction('delete')
        ->assertActionNotMounted()
        ->callMountedAction();

    $this->assertModelExists($dataset);
});

it('allows deleting custom datasets from the table and edit page', function (): void {
    $tableDataset = Dataset::factory()->create(['rec_code' => null]);
    $editDataset = Dataset::factory()->create(['rec_code' => null]);

    livewire(ManageDatasets::class)
        ->assertTableActionVisible('delete', $tableDataset)
        ->callAction(TestAction::make('delete')->table($tableDataset));

    livewire(EditDataset::class, ['record' => $editDataset->getKey()])
        ->assertActionVisible('delete')
        ->callAction('delete');

    $this->assertModelMissing($tableDataset);
    $this->assertModelMissing($editDataset);
});

it('hides the portal metadata section for datasets without a saved portal identifier', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => null,
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertSchemaComponentHidden('portalMetadata');
});

it('displays the update frequency as a label without hiding unrecognized portal values', function (string $frequency, string $display): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001259',
    ]);
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => $frequency,
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertSee($display);
})->with([
    'known portal code' => ['once a year', 'Щороку'],
    'unknown portal value' => ['special schedule', 'special schedule'],
]);

it('does not allow non-administrators to create datasets when the portal is configured', function (): void {
    config(['services.open_data.organization_id' => '11111111-2222-4333-8444-555555555555']);
    $this->actingAs(User::factory()->create());

    livewire(ManageDatasets::class)
        ->assertActionHidden(TestAction::make('create'));
});

it('edits dataset details on its edit page', function () {
    $publisher = Organization::factory()->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001259',
        ]);
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'once a year',
        'keywords' => ['оновлений реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    Http::preventStrayRequests();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm([
            'title' => 'Оновлений реєстр',
            'rec_code' => DatasetRecommendation::LocalDeputies->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->title)->toBe('Оновлений реєстр')
        ->and($dataset->rec_code)->toBe(DatasetRecommendation::LocalDeputies)
        ->and($dataset->meta->keywords)->toBe(['оновлений реєстр']);

    Http::assertNothingSent();
});

it('saves the dataset status and information owner without changing its publisher', function (): void {
    $publisher = Organization::factory()->create();
    $dataset = Dataset::factory()->for($publisher, 'publisher')->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm([
            'status' => DatasetStatus::Collecting->value,
            'status_note' => null,
            'info_owner' => 'Відділ цифрового розвитку',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->status)->toBe(DatasetStatus::Collecting)
        ->and($dataset->status_note)->toBeNull()
        ->and($dataset->info_owner)->toBe('Відділ цифрового розвитку')
        ->and($dataset->publisher_id)->toBe($publisher->getKey());
});

it('requires a comment for statuses that need an explanation', function (DatasetStatus $status): void {
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm([
            'status' => $status->value,
            'status_note' => null,
        ])
        ->call('save')
        ->assertHasFormErrors(['status_note' => 'required_if']);
})->with([
    'no information' => DatasetStatus::NoInformation,
    'not published' => DatasetStatus::NotPublished,
]);

it('shows the status, its comment, and information owner in the dataset table', function (): void {
    $dataset = Dataset::factory()->create([
        'status' => DatasetStatus::NotPublished,
        'status_note' => 'Не публікується на період воєнного стану.',
        'info_owner' => 'Відділ цифрового розвитку',
    ]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertSee('dataset-status-not-published', false)
        ->assertSee('Не публікується')
        ->assertSee('Не публікується на період воєнного стану.')
        ->assertSee('Відділ цифрового розвитку');
});

it('highlights unknown datasets and uses a neutral color for datasets without information', function (): void {
    expect(DatasetStatus::Unknown->getColor())->toBe('warning')
        ->and(DatasetStatus::NoInformation->getColor())->toBe('gray');
});

it('filters datasets by status and restores all records when cleared', function (): void {
    $unknown = Dataset::factory()->create(['status' => DatasetStatus::Unknown]);
    $published = Dataset::factory()->create(['status' => DatasetStatus::Published]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$unknown, $published])
        ->filterTable('status', DatasetStatus::Published->value)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$unknown])
        ->removeTableFilter('status')
        ->assertCanSeeTableRecords([$unknown, $published]);
});

it('creates and assigns a group from the dataset form dropdown', function (): void {
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callFormComponentAction('group_id', 'createOption', [
            'name' => 'Звіти та фінанси',
        ])
        ->assertHasNoFormErrors()
        ->call('save')
        ->assertHasNoFormErrors();

    $group = DatasetGroup::query()->where('name', 'Звіти та фінанси')->firstOrFail();

    expect($dataset->refresh()->group_id)->toBe($group->getKey())
        ->and($dataset->group->name)->toBe('Звіти та фінанси')
        ->and($group->sort)->toBe(1);
});

it('renames a group from the dataset form dropdown', function (): void {
    $group = DatasetGroup::factory()->create(['name' => 'Попередня назва']);
    $dataset = Dataset::factory()->for($group, 'group')->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->callFormComponentAction('group_id', 'editOption', [
            'name' => 'Оновлена назва',
            'sort' => 5,
        ])
        ->assertHasNoFormErrors();

    expect($group->refresh()->name)->toBe('Оновлена назва')
        ->and($group->sort)->toBe(5);
});

it('assigns each new dataset the next order within its group', function (): void {
    $group = DatasetGroup::factory()->create();
    $first = Dataset::factory()->for($group, 'group')->create();
    $second = Dataset::factory()->for($group, 'group')->create();

    expect($first->sort)->toBe(1)
        ->and($second->sort)->toBe(2);
});

it('reorders datasets using the manual order field', function (): void {
    $group = DatasetGroup::factory()->create(['name' => 'Рішення ради']);
    $first = Dataset::factory()->for($group, 'group')->create(['sort' => 1]);
    $second = Dataset::factory()->for($group, 'group')->create(['sort' => 2]);
    $otherGroup = DatasetGroup::factory()->create(['name' => 'Звіти']);
    $third = Dataset::factory()->for($otherGroup, 'group')->create(['sort' => 1]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$first, $second, $third])
        ->assertSee('Рішення ради')
        ->assertSee('Звіти')
        ->call('reorderTable', [$second->getKey(), $first->getKey(), $third->getKey()]);

    expect($second->refresh()->sort)->toBe(1)
        ->and($first->refresh()->sort)->toBe(2)
        ->and($third->refresh()->sort)->toBe(3);
});

it('allows a dataset recommendation to remain unchanged or be cleared', function (): void {
    $dataset = Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->fillForm(['rec_code' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('rejects a recommendation already assigned to another dataset', function (): void {
    Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm(['rec_code' => DatasetRecommendation::LocalDeputies->value])
        ->call('save')
        ->assertHasFormErrors(['rec_code' => 'unique']);

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('rejects an unknown dataset recommendation', function (): void {
    $dataset = Dataset::factory()->create();

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->fillForm(['rec_code' => 'unknown_recommendation'])
        ->call('save')
        ->assertHasFormErrors(['rec_code']);

    expect($dataset->refresh()->rec_code)->toBeNull();
});

it('allows multiple custom datasets without a recommendation', function (): void {
    $datasets = Dataset::factory()->count(2)->create();

    expect($datasets->first()->rec_code)->toBeNull()
        ->and($datasets->last()->rec_code)->toBeNull();
});

it('enforces recommendation uniqueness in the database', function (): void {
    Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]);

    expect(fn () => Dataset::factory()->create(['rec_code' => DatasetRecommendation::LocalDeputies]))
        ->toThrow(QueryException::class);
});

it('provides official labels and documentation links for the recommendations', function (): void {
    expect(DatasetRecommendation::cases())->toHaveCount(75)
        ->and(DatasetRecommendation::LocalDeputies->getLabel())
        ->toBe('Дані про депутатів місцевих рад, зокрема контактна інформація та графік прийому')
        ->and(DatasetRecommendation::LocalDeputies->getExternalLink())
        ->toBe('https://data.gov.ua/pages/835-recm-local-government-deputies')
        ->and(DatasetRecommendation::Playgrounds->getExternalLink())
        ->toBe('https://data.gov.ua/pages/835-recm-budget-budget-programs')
        ->and(DatasetRecommendation::PreschoolCatchmentAreas->getExternalLink())
        ->toBe('https://data.gov.ua/pages/terytorii-obsluhovuvannia-komunalnykh-zakladiv-doshkilnoi-osvity')
        ->and(DatasetRecommendation::BurialRegisters->getLabel())
        ->toBe('Перелік книг обліку реєстрації поховань');
});

it('opens the dataset edit page from the table action', function (): void {
    $dataset = Dataset::factory()->create();
    $editUrl = DatasetResource::getUrl('edit', ['record' => $dataset]);

    livewire(ManageDatasets::class)
        ->assertTableActionHasUrl(
            'edit',
            $editUrl,
            $dataset,
        )
        ->assertSee('href="'.$editUrl.'"', false);
});

it('shows external links on the dataset edit page', function (): void {
    config(['services.open_data.base_url' => 'https://portal.example.test']);

    $dataset = Dataset::factory()->create([
        'rec_code' => DatasetRecommendation::LocalDeputies,
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertActionVisible('documentationLink')
        ->assertActionHasUrl('documentationLink', 'https://data.gov.ua/pages/835-recm-local-government-deputies')
        ->assertActionShouldOpenUrlInNewTab('documentationLink')
        ->assertActionVisible('portalLink')
        ->assertActionHasUrl('portalLink', 'https://portal.example.test/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252')
        ->assertActionShouldOpenUrlInNewTab('portalLink');
});

it('hides unavailable external links on the dataset edit page', function (): void {
    $dataset = Dataset::factory()->create([
        'rec_code' => null,
        'external_id' => null,
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertActionHidden('documentationLink')
        ->assertActionHidden('portalLink');
});

it('shows portal resource management on the dataset edit page', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertSeeLivewire(ResourcesRelationManager::class);
});

it('hides portal resource management when the dataset is not linked to the portal', function (): void {
    $dataset = Dataset::factory()->create();

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertDontSeeLivewire(ResourcesRelationManager::class);
});

it('hides portal resource management from non-administrators', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $this->actingAs(User::factory()->create());

    $this->get(DatasetResource::getUrl('edit', ['record' => $dataset]))
        ->assertOk()
        ->assertDontSeeLivewire(ResourcesRelationManager::class);
});

it('synchronizes portal resources and removes local resources that no longer exist', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $obsoleteResource = $dataset->resources()->create([
        'external_id' => 'obsolete-resource-id',
        'name' => 'obsolete.csv',
        'format' => 'CSV',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-package-id',
                'resources' => [
                    [
                        'id' => 'reports-archive-id',
                        'name' => 'reports.zip',
                        'format' => 'ZIP',
                        'size' => 2048,
                        'description' => 'Архів звітів',
                        'created' => '2024-01-02T03:04:05',
                        'last_modified' => '2024-06-07T08:09:10',
                    ],
                    ['id' => 'reports-list-id', 'name' => 'listOfReports.csv', 'format' => 'CSV'],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('sync')
        ->assertNotified();

    $this->assertDatabaseHas('dataset_resources', [
        'dataset_id' => $dataset->getKey(),
        'external_id' => 'reports-archive-id',
        'name' => 'reports.zip',
        'format' => 'ZIP',
        'size' => 2048,
        'description' => 'Архів звітів',
        'created_at' => '2024-01-02 03:04:05',
        'updated_at' => '2024-06-07 08:09:10',
    ]);
    $this->assertDatabaseHas('dataset_resources', [
        'dataset_id' => $dataset->getKey(),
        'external_id' => 'reports-list-id',
        'name' => 'listOfReports.csv',
        'format' => 'CSV',
    ]);
    $this->assertDatabaseMissing('dataset_resources', [
        'id' => $obsoleteResource->getKey(),
    ]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'dataset-package-id');
});

it('updates an existing resource using the portal resource with the same external ID', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $resource = $dataset->resources()->create([
        'external_id' => 'existing-resource-id',
        'name' => 'old-name.csv',
        'format' => 'XML',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'dataset-package-id',
                'resources' => [
                    [
                        'id' => 'existing-resource-id',
                        'name' => 'posts.csv',
                        'format' => 'JSON',
                        'size' => 1536,
                        'description' => 'Опис ресурсу',
                        'created' => '2025-01-02T03:04:05',
                        'last_modified' => '2025-06-07T08:09:10',
                    ],
                ],
            ],
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->callTableAction('sync')
        ->assertNotified();

    expect($resource->refresh()->external_id)->toBe('existing-resource-id')
        ->and($resource->name)->toBe('posts.csv')
        ->and($resource->format)->toBe('JSON')
        ->and($resource->size)->toBe(1536)
        ->and($resource->description)->toBe('Опис ресурсу')
        ->and($resource->created_at->format('Y-m-d H:i:s'))->toBe('2025-01-02 03:04:05')
        ->and($resource->updated_at->format('Y-m-d H:i:s'))->toBe('2025-06-07 08:09:10');

    expect($dataset->resources()->count())->toBe(1);
});

it('links each resource row to its portal resource page', function (): void {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);
    $dataset = Dataset::factory()->create([
        'external_id' => 'dataset-package-id',
    ]);
    $resource = DatasetResourceModel::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'portal-resource-id',
            'name' => 'organizations.csv',
        ]);

    $url = 'https://portal.example.test/dataset/dataset-package-id/resource/portal-resource-id';

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->assertTableActionExists('openPortalResource', record: $resource)
        ->assertTableActionHasUrl('openPortalResource', $url, $resource);
});

it('provides a portal download link with an encoded resource name', function (): void {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResourceModel::factory()->for($dataset)->create([
        'external_id' => 'portal-resource-id',
        'name' => 'дані за рік.csv',
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->assertTableActionHasUrl(
            'downloadResource',
            'https://portal.example.test/dataset/dataset-package-id/resource/portal-resource-id/download/%D0%B4%D0%B0%D0%BD%D1%96%20%D0%B7%D0%B0%20%D1%80%D1%96%D0%BA.csv',
            $resource,
        );
});

it('starts a data archive import from the resource row', function (): void {
    config([
        'services.open_data.base_url' => 'https://data.gov.ua',
        'services.open_data.api_token' => 'test-token',
    ]);
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResourceModel::factory()->for($dataset)->create([
        'external_id' => 'portal-resource-id',
        'name' => 'register.csv',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/datapusher_submit' => Http::response([
            'success' => true,
            'result' => true,
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->assertTableActionExists('uploadToArchive', fn (Action $action): bool => $action->isConfirmationRequired(), $resource)
        ->mountTableAction('uploadToArchive', $resource)
        ->callMountedTableAction()
        ->assertNotified('Завантаження в архів даних розпочато');

    Http::assertSentCount(1);
});

it('notifies when the portal does not accept a data archive import', function (): void {
    config([
        'services.open_data.base_url' => 'https://data.gov.ua',
        'services.open_data.api_token' => 'test-token',
    ]);
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResourceModel::factory()->for($dataset)->create([
        'external_id' => 'portal-resource-id',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/datapusher_submit' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->mountTableAction('uploadToArchive', $resource)
        ->callMountedTableAction()
        ->assertNotified('Портал не запустив завантаження');

    Http::assertSentCount(1);
});

it('shows resource size and description instead of the identifier', function (): void {
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    DatasetResourceModel::factory()->for($dataset)->create([
        'name' => 'register.csv',
        'size' => 1536,
        'description' => 'Опис реєстру',
    ]);

    livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])
        ->assertTableColumnExists('size')
        ->assertTableColumnExists('created_at')
        ->assertTableColumnDoesNotExist('external_id')
        ->assertSee('1.5 KB');
});

it('shows an empty description and inactive datastore in the resource view', function (): void {
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResourceModel::factory()->for($dataset)->create([
        'external_id' => 'portal-resource-id',
        'description' => null,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/resource_show*' => Http::response(['success' => true, 'result' => ['datastore_active' => false]]),
        'data.gov.ua/api/3/action/resource_view_list*' => Http::response(['success' => true, 'result' => []]),
    ]);

    $component = livewire(ResourcesRelationManager::class, [
        'ownerRecord' => $dataset,
        'pageClass' => EditDataset::class,
    ])->mountTableAction('view', $resource)
        ->assertSchemaStateSet([
            'description' => null,
            'datastore_status' => 'Дані не завантажено',
            'total' => null,
            'view_count' => 0,
        ]);

    $livewire = $component->instance();
    $html = $livewire->getSchema($livewire->getMountedActionSchemaName())->toHtml();
    expect($html)->toContain('Опис')
        ->toContain('—')
        ->toContain('Дані не завантажено');

    Http::assertSentCount(2);
});

it('generates the dataset page link from the configured open data portal URL', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test/']);

    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);

    expect($dataset->url)->toBe('https://portal.example.test/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252');

    expect(Schema::hasColumn('datasets', 'url'))->toBeFalse();
});

it('renders the dataset portal link as an icon action', function () {
    config(['services.open_data.base_url' => 'https://portal.example.test']);

    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);

    livewire(ManageDatasets::class)
        ->assertTableActionExists('portalLink', record: $dataset)
        ->assertTableActionHasIcon('portalLink', Heroicon::OutlinedArrowTopRightOnSquare, $dataset)
        ->assertTableActionHasUrl('portalLink', $dataset->url, $dataset)
        ->assertTableActionShouldOpenUrlInNewTab('portalLink', $dataset);
});

it('does not render a portal link for datasets without an external identifier', function (): void {
    $dataset = Dataset::factory()->create();

    expect($dataset->url)->toBeNull();

    livewire(ManageDatasets::class)
        ->assertTableActionHidden('portalLink', $dataset);
});

it('stores dataset metadata in its own related table', function (): void {
    $nullableMetadataColumns = collect(Schema::getColumns('dataset_metas'))
        ->whereIn('name', [
            'description',
            'accrual_periodicity',
            'keywords',
            'purpose',
            'contact_point_name',
            'contact_point_email',
        ])
        ->where('nullable', true)
        ->pluck('name')
        ->all();

    expect(Schema::hasTable('dataset_metas'))->toBeTrue()
        ->and(Schema::hasColumn('datasets', 'responsible_official_id'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'official_title'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'description'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'accrual_periodicity'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'keywords'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'purpose'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'distribution_format'))->toBeFalse()
        ->and(Schema::hasColumn('dataset_metas', 'contact_point_name'))->toBeTrue()
        ->and(Schema::hasColumn('dataset_metas', 'contact_point_email'))->toBeTrue()
        ->and(Schema::hasColumn('datasets', 'description'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'accrual_periodicity'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'keywords'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'purpose'))->toBeFalse()
        ->and(Schema::hasColumn('datasets', 'distribution_formats'))->toBeFalse()
        ->and($nullableMetadataColumns)->toBe([]);
});

it('does not display update frequency in the datasets table', function (): void {
    $dataset = Dataset::factory()->create();
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'once a year',
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertDontSee('Щороку');
});

it('does not display resource formats in dataset metadata', function () {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
    ]);
    $dataset->meta()->create([
        'description' => 'Опис набору.',
        'accrual_periodicity' => 'once a year',
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт',
        'contact_point_email' => 'contact@example.gov.ua',
    ]);

    livewire(EditDataset::class, ['record' => $dataset->getKey()])
        ->assertDontSee('Формати ресурсів');
});

it('renders a documentation action that opens the recommendation in a new tab', function (): void {
    $dataset = Dataset::factory()->create([
        'rec_code' => DatasetRecommendation::LocalDeputies,
    ]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertTableActionVisible('documentationLink', $dataset)
        ->assertTableActionHasIcon('documentationLink', Heroicon::OutlinedInformationCircle, $dataset)
        ->assertTableActionHasUrl(
            'documentationLink',
            'https://data.gov.ua/pages/835-recm-local-government-deputies',
            $dataset,
        )
        ->assertTableActionShouldOpenUrlInNewTab('documentationLink', $dataset);
});

it('hides the documentation action for custom datasets', function (): void {
    $dataset = Dataset::factory()->create(['rec_code' => null]);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertTableActionHidden('documentationLink', $dataset);
});

it('renders the datasets page with its order column', function () {
    $publisher = Organization::factory()->create();
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create(['title' => 'Набір для перевірки']);

    livewire(ManageDatasets::class)
        ->assertCanSeeTableRecords([$dataset])
        ->assertSee('Набори даних')
        ->assertSee('№')
        ->assertTableColumnStateSet('sort', $dataset->sort, $dataset);

    $this->get(DatasetResource::getUrl())
        ->assertOk()
        ->assertSee('Набори даних');
});

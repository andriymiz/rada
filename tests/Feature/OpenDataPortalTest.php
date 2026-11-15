<?php

use App\Enums\DatasetResourceType;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('extracts distinct formats from resources linked to the package', function (): void {
    $packageId = 'c65f219b-4f4e-4e51-8dc6-7a1df3001252';

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => $packageId,
                'resources' => [
                    ['id' => 'csv-resource', 'format' => 'CSV'],
                    ['id' => 'json-resource', 'format' => 'JSON'],
                    ['id' => 'csv-copy', 'format' => 'CSV'],
                    ['id' => 'unlinked-resource', 'format' => 'PDF'],
                ],
            ],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->getPackageResourceFormats($packageId, [
        'csv-resource',
        'json-resource',
        'csv-copy',
    ]))
        ->toBe(['CSV', 'JSON']);
});

it('searches resources only within the selected dataset', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'deputies-dataset-id',
                'resources' => [
                    [
                        'id' => 'deputies-resource-id',
                        'name' => 'Депутати',
                        'description' => 'Список депутатів',
                        'format' => 'CSV',
                    ],
                    [
                        'id' => 'posts-resource-id',
                        'name' => 'Посади',
                        'description' => 'Список посад',
                        'format' => 'CSV',
                    ],
                ],
            ],
        ]),
    ]);

    $resources = app(OpenDataPortal::class)->searchResources('deputies-dataset-id', 'депутат');

    expect($resources)->toBe(['deputies-resource-id' => 'Депутати (CSV)']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'deputies-dataset-id');
});

it('gets the resource download URL from its related dataset and type', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'deputies-dataset-id',
    ]);
    DatasetResource::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'deputies-resource-id',
            'name' => DatasetResourceType::Deputies,
        ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'deputies-dataset-id',
                'resources' => [
                    [
                        'id' => 'deputies-resource-id',
                        'url' => 'https://data.gov.ua/dataset/deputies/resource/deputies.csv',
                    ],
                ],
            ],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->getResourceDownloadUrl(DatasetResourceType::Deputies))
        ->toBe('https://data.gov.ua/dataset/deputies/resource/deputies.csv');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/package_show')
        && $request->data()['id'] === 'deputies-dataset-id');
});

it('downloads report resources by their exact portal names regardless of file extension', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'reports-dataset-id',
    ]);
    DatasetResource::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'reports-archive-id',
            'name' => DatasetResourceType::Reports,
        ]);
    DatasetResource::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'reports-list-id',
            'name' => DatasetResourceType::ListOfReports,
        ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'reports-dataset-id',
                'resources' => [
                    [
                        'id' => 'reports-archive-id',
                        'url' => 'https://data.gov.ua/reports.zip',
                    ],
                    [
                        'id' => 'reports-list-id',
                        'url' => 'https://data.gov.ua/listOfReports.csv',
                    ],
                ],
            ],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->getResourceDownloadUrl(DatasetResourceType::Reports))
        ->toBe('https://data.gov.ua/reports.zip');
    expect(app(OpenDataPortal::class)->getResourceDownloadUrl(DatasetResourceType::ListOfReports))
        ->toBe('https://data.gov.ua/listOfReports.csv');
});

it('removes all local resources when the portal package has no resources', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'empty-package-id',
    ]);
    $resource = DatasetResource::factory()
        ->for($dataset)
        ->create([
            'external_id' => 'obsolete-resource-id',
        ]);

    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'empty-package-id',
                'resources' => [],
            ],
        ]),
    ]);

    expect(app(OpenDataPortal::class)->syncDatasetResources($dataset))->toBe(0);

    $this->assertDatabaseMissing('dataset_resources', [
        'id' => $resource->getKey(),
    ]);
});

it('clears resource metadata and dates when the portal does not provide them', function (): void {
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResource::factory()->for($dataset)->create([
        'external_id' => 'resource-id',
        'size' => 100,
        'description' => 'Старий опис',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => ['resources' => [['id' => 'resource-id', 'name' => 'register.csv']]],
        ]),
    ]);

    app(OpenDataPortal::class)->syncDatasetResources($dataset);

    expect($resource->refresh()->size)->toBe(0)
        ->and($resource->description)->toBeNull()
        ->and($resource->created_at)->toBeNull()
        ->and($resource->updated_at)->toBeNull();
});

it('does not request datastore records for a resource without a datastore', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/resource_show*' => Http::response(['success' => true, 'result' => ['datastore_active' => false]]),
        'data.gov.ua/api/3/action/resource_view_list*' => Http::response(['success' => true, 'result' => []]),
    ]);

    $details = app(OpenDataPortal::class)->getResourceDetails('resource-id');

    expect($details['datastore_status'])->toBe('Дані не завантажено')
        ->and($details['total'])->toBeNull()
        ->and($details['view_count'])->toBe(0);
    Http::assertSentCount(2);
});

it('submits a resource for forced import into the data archive', function (): void {
    config([
        'services.open_data.base_url' => 'https://data.gov.ua',
        'services.open_data.api_token' => 'test-token',
    ]);
    $resource = DatasetResource::factory()->create(['external_id' => 'portal-resource-id']);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/datapusher_submit' => Http::response([
            'success' => true,
            'result' => true,
        ]),
    ]);

    expect(app(OpenDataPortal::class)->submitResourceToArchive($resource))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://data.gov.ua/api/3/action/datapusher_submit'
        && $request->hasHeader('Authorization', 'test-token')
        && $request->data() === [
            'resource_id' => 'portal-resource-id',
            'ignore_hash' => true,
        ]);
});

it('returns false when the portal does not accept a data archive import', function (): void {
    config([
        'services.open_data.base_url' => 'https://data.gov.ua',
        'services.open_data.api_token' => 'test-token',
    ]);
    $resource = DatasetResource::factory()->create(['external_id' => 'portal-resource-id']);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/datapusher_submit' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    expect(app(OpenDataPortal::class)->submitResourceToArchive($resource))->toBeFalse();

    Http::assertSentCount(1);
});

it('rejects invalid resource metadata without changing existing resources', function (array $invalidMetadata): void {
    $dataset = Dataset::factory()->create(['external_id' => 'dataset-package-id']);
    $resource = DatasetResource::factory()->for($dataset)->create(['external_id' => 'resource-id', 'name' => 'old.csv']);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/package_show*' => Http::response([
            'success' => true,
            'result' => ['resources' => [['id' => 'resource-id', 'name' => 'register.csv', ...$invalidMetadata]]],
        ]),
    ]);

    expect(fn () => app(OpenDataPortal::class)->syncDatasetResources($dataset))
        ->toThrow(UnexpectedValueException::class);

    expect($resource->refresh()->name)->not->toBe('register.csv');
})->with([
    'negative size' => [['size' => -1]],
    'invalid creation date' => [['created' => 'not-a-date']],
]);

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

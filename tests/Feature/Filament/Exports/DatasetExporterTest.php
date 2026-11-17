<?php

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetResourceType;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Exports\DatasetExporter;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Jobs\UploadDatasetRegister;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\OpenDataPortal;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Jobs\CompleteExport;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Csv\Bom;
use League\Csv\Reader;
use Psr\Http\Message\StreamInterface;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

function exporterMetadata(array $overrides = []): array
{
    return array_replace([
        'description' => 'Опис набору.',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр'],
        'purpose' => 'Підстава.',
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal@example.gov.ua',
    ], $overrides);
}

it('exports dataset rows using the open data register schema', function (): void {
    config(['services.open_data.base_url' => 'https://data.gov.ua']);

    $publisher = Organization::factory()->create([
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
        'edrpou' => '04058410',
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
            'title' => 'Реєстр',
            'rec_code' => DatasetRecommendation::LocalDeputies,
        ]);
    $dataset->resources()->createMany([
        [
            'external_id' => 'csv-resource',
            'name' => DatasetResourceType::Deputies,
            'format' => 'CSV',
        ],
        [
            'external_id' => 'json-resource',
            'name' => DatasetResourceType::Posts,
            'format' => 'JSON',
        ],
        [
            'external_id' => 'csv-copy',
            'name' => DatasetResourceType::Organizations,
            'format' => 'CSV',
        ],
    ]);
    Http::preventStrayRequests();
    $dataset->meta()->create(exporterMetadata([
        'description' => 'Перелік наборів даних розпорядника.',
        'accrual_periodicity' => DatasetUpdateFrequency::Yearly->value,
        'keywords' => ['реєстр', 'набори даних'],
        'purpose' => 'Закон України «Про доступ до публічної інформації».',
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(DatasetExporter::test()->export($dataset))->toBe([
        'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'Дані про депутатів місцевих рад, зокрема контактна інформація та графік прийому',
        'Перелік наборів даних розпорядника.',
        'Щороку',
        'реєстр, набори даних',
        'Закон України «Про доступ до публічної інформації».',
        'https://data.gov.ua/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'CSV, JSON',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        '04058410',
        'Контакт порталу',
        'portal@example.gov.ua',
    ]);

    expect(collect(DatasetExporter::getColumns())->map->getLabel()->all())->toBe([
        'uid',
        'title',
        'description',
        'accrualPeriodicity',
        'keywords',
        'purpose',
        'url',
        'distributionFormat',
        'publisherName',
        'publisherId',
        'contactPointName',
        'contactPointEmail',
    ]);
});

it('exports metadata contact values without using the responsible official', function (): void {
    $publisher = Organization::factory()->create([
        'edrpou' => 'null',
    ]);
    $dataset = Dataset::factory()
        ->for($publisher, 'publisher')
        ->create([
            'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001253',
        ]);
    $dataset->meta()->create(exporterMetadata([
        'contact_point_name' => 'Контакт із метаданих',
        'contact_point_email' => 'metadata@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 9))
        ->toBe([
            'null',
            'Контакт із метаданих',
            'metadata@example.gov.ua',
        ]);
});

it('exports dataset metadata stored in its related record', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'title' => 'Коротка назва',
    ]);
    $dataset->meta()->create(exporterMetadata([
        'description' => 'Опис із порталу',
        'accrual_periodicity' => 'once a year',
        'keywords' => ['реєстр', 'дані'],
        'purpose' => 'Підстава.',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 1, 7))->toBe([
        'Коротка назва',
        'Опис із порталу',
        'Щороку',
        'реєстр, дані',
        'Підстава.',
        'https://data.gov.ua/dataset/c65f219b-4f4e-4e51-8dc6-7a1df3001252',
        'null',
    ]);
});

it('exports contact information from the related metadata when present', function (): void {
    $dataset = Dataset::factory()->create([
        'external_id' => 'c65f219b-4f4e-4e51-8dc6-7a1df3001254',
    ]);
    $dataset->meta()->create(exporterMetadata([
        'contact_point_name' => 'Контакт порталу',
        'contact_point_email' => 'portal-contact@example.gov.ua',
    ]));
    $dataset->load(['meta', 'publisher']);

    expect(array_slice(DatasetExporter::test()->export($dataset), 10))
        ->toBe(['Контакт порталу', 'portal-contact@example.gov.ua']);
});

it('exports only the complete register schema as a CSV resource', function (): void {
    livewire(ManageDatasets::class)
        ->assertActionExists('export', fn (ExportAction $action): bool => $action->getExporter() === DatasetExporter::class
            && $action->getFileName(new Export) === 'register'
            && $action->getFormats() === [ExportFormat::Csv]
            && ! $action->hasColumnMapping())
        ->assertActionDoesNotExist('publishRegister');
});

it('uploads a completed dataset export when the option is selected', function (): void {
    config(['queue.default' => 'sync']);
    config(['services.open_data.base_url' => 'https://data.gov.ua', 'services.open_data.api_token' => 'test-token']);
    Storage::fake('local');
    Queue::fake([UploadDatasetRegister::class]);
    Log::spy();
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/resource_patch' => function (Request $request) {
            $upload = collect($request->data())->first(fn (array $part): bool => $part['name'] === 'upload');
            $export = Export::query()->sole();
            $contents = implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export)));
            $body = $request->toPsrRequest()->getBody()->getContents();
            expect($upload['contents'])->toBeInstanceOf(StreamInterface::class)
                ->and($body)->toContain($contents)
                ->and($request->hasHeader('Content-Length'))->toBeFalse()
                ->and($request->header('Transfer-Encoding'))->toBe(['chunked'])
                ->and($request->hasHeader('Expect'))->toBeFalse();

            return Http::response([
                'success' => true,
                'result' => ['id' => 'register-resource'],
            ]);
        },
    ]);
    $userId = auth()->id();

    $publisher = Organization::factory()->create(['name' => 'Розпорядник', 'edrpou' => '04058410']);
    $first = Dataset::factory()->for($publisher, 'publisher')->create([
        'external_id' => 'first-id',
        'title' => 'Перший набір',
    ]);
    $first->meta()->create(exporterMetadata(['contact_point_email' => 'updated@example.gov.ua']));
    $first->resources()->create([
        'external_id' => 'csv-resource',
        'name' => DatasetResourceType::Deputies,
        'format' => 'CSV',
    ]);
    DatasetResource::factory()->for($first)->create([
        'external_id' => 'register-resource',
        'name' => DatasetResourceType::Register,
        'format' => 'CSV',
    ]);
    $second = Dataset::factory()->for($publisher, 'publisher')->create([
        'external_id' => 'second-id',
        'title' => 'Другий набір',
    ]);
    $second->meta()->create(exporterMetadata());
    Dataset::factory()->for($publisher, 'publisher')->create(['external_id' => 'missing-meta']);
    $missingId = Dataset::factory()->for($publisher, 'publisher')->create(['external_id' => null]);
    $missingId->meta()->create(exporterMetadata());

    livewire(ManageDatasets::class)
        ->callAction('export', data: ['upload_to_portal' => true])
        ->assertNotified();

    $export = Export::query()->sole();
    expect($export->user_id)->toBe($userId)
        ->and((bool) $export->upload_to_portal)->toBeTrue()
        ->and($export->total_rows)->toBe(2)
        ->and($export->processed_rows)->toBe(2)
        ->and($export->successful_rows)->toBe(2)
        ->and($export->completed_at)->not->toBeNull();
    Queue::assertPushed(UploadDatasetRegister::class, fn (UploadDatasetRegister $job): bool => $job->export->is($export));
    $job = Queue::pushed(UploadDatasetRegister::class)->first();
    unserialize(serialize($job))->handle(app(OpenDataPortal::class));

    $notification = $export->user->notifications()
        ->where('data->title', 'Реєстр завантажено на портал')
        ->sole();
    expect($notification->data['format'])->toBe('filament')
        ->and($notification->data['status'])->toBe('success');

    $contents = implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export)));
    Storage::disk('local')->assertMissing($export->getFileDirectory().'/register.csv');
    expect($contents)->toStartWith(Bom::Utf8->value);
    $csv = Reader::fromString($contents);
    $csv->setHeaderOffset(0);
    expect($csv->getHeader())->toBe([
        'uid', 'title', 'description', 'accrualPeriodicity', 'keywords', 'purpose',
        'url', 'distributionFormat', 'publisherName', 'publisherId', 'contactPointName', 'contactPointEmail',
    ]);
    $rows = array_values(iterator_to_array($csv->getRecords()));
    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'uid'))->toBe(['first-id', 'second-id'])
        ->and($rows[0]['contactPointEmail'])->toBe('updated@example.gov.ua')
        ->and($rows[0]['distributionFormat'])->toBe('CSV')
        ->and($rows[1]['title'])->toBe('Другий набір');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://data.gov.ua/api/3/action/resource_patch'
        && $request->hasHeader('Authorization', 'test-token')
        && $request->hasFile('upload', filename: 'register.csv')
        && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'id' && $part['contents'] === 'register-resource'));
});

it('notifies the export owner and fails the job when portal upload throws', function (bool $resourceExists): void {
    config(['services.open_data.base_url' => 'https://data.gov.ua', 'services.open_data.api_token' => 'test-token']);
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['data.gov.ua/api/3/action/resource_patch' => Http::response([], 500)]);

    $owner = User::factory()->admin()->create();
    $export = new Export([
        'file_disk' => 'local',
        'exporter' => DatasetExporter::class,
        'total_rows' => 1,
        'user_id' => $owner->id,
    ]);
    $export->save();
    Storage::disk('local')->put($export->getFileDirectory().'/headers.csv', "uid\n");

    if ($resourceExists) {
        DatasetResource::factory()->create(['name' => DatasetResourceType::Register, 'external_id' => 'register-resource']);
    }

    expect(fn () => (new UploadDatasetRegister($export))->handle(app(OpenDataPortal::class)))
        ->toThrow($resourceExists ? RequestException::class : TypeError::class);

    $notification = $owner->notifications()->sole();
    expect($notification->data['title'])->toBe('Помилка завантаження на портал')
        ->and($notification->data['status'])->toBe('danger')
        ->and($notification->data['format'])->toBe('filament')
        ->and(auth()->user()->notifications()->count())->toBe(0);
})->with([true, false]);

it('does not prepare a portal register when the upload option is unchecked or absent', function (array $options): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    Queue::fake([UploadDatasetRegister::class]);
    $publisher = Organization::factory()->create();
    $dataset = Dataset::factory()->for($publisher, 'publisher')->create(['external_id' => 'first-id']);
    $dataset->meta()->create(exporterMetadata());

    livewire(ManageDatasets::class)
        ->callAction('export', data: $options)
        ->assertNotified();

    $export = Export::query()->sole();
    expect((bool) $export->upload_to_portal)->toBeFalse();
    Storage::disk('local')->assertMissing($export->getFileDirectory().'/register.csv');
    expect(implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export))))->toContain('first-id');
    Queue::assertNotPushed(UploadDatasetRegister::class);
})->with([[['upload_to_portal' => false]], [[]]]);

it('uploads the filtered export when portal upload is selected', function (): void {
    config(['queue.default' => 'sync', 'services.open_data.base_url' => 'https://data.gov.ua', 'services.open_data.api_token' => 'test-token']);
    Storage::fake('local');
    Queue::fake([UploadDatasetRegister::class]);
    Http::preventStrayRequests();
    Http::fake([
        'data.gov.ua/api/3/action/resource_patch' => function (Request $request) {
            $body = $request->toPsrRequest()->getBody()->getContents();
            expect($body)->toContain('included-id')->not->toContain('excluded-id');

            return Http::response(['success' => true, 'result' => ['id' => 'register-resource']]);
        },
    ]);
    $publisher = Organization::factory()->create();
    $included = Dataset::factory()->for($publisher, 'publisher')->create(['external_id' => 'included-id', 'title' => 'Included dataset']);
    $included->meta()->create(exporterMetadata());
    DatasetResource::factory()->for($included)->create(['name' => DatasetResourceType::Register, 'external_id' => 'register-resource']);
    $excluded = Dataset::factory()->for($publisher, 'publisher')->create(['external_id' => 'excluded-id', 'title' => 'Other dataset']);
    $excluded->meta()->create(exporterMetadata());

    livewire(ManageDatasets::class)
        ->searchTable('Included dataset')
        ->callAction('export', data: ['upload_to_portal' => true])
        ->assertNotified();

    Queue::assertPushed(UploadDatasetRegister::class);
    $job = Queue::pushed(UploadDatasetRegister::class)->first();
    $job->handle(app(OpenDataPortal::class));
    Storage::disk('local')->assertMissing($job->export->getFileDirectory().'/register.csv');
    Http::assertSentCount(1);
});

it('uses the saved upload decision when the completion job is serialized', function (bool $uploadToPortal): void {
    config(['queue.default' => 'sync']);
    Queue::fake([UploadDatasetRegister::class]);
    $export = new Export([
        'file_disk' => 'local',
        'exporter' => DatasetExporter::class,
        'total_rows' => 1,
        'processed_rows' => 1,
        'successful_rows' => 1,
        'user_id' => auth()->id(),
    ]);
    $export->options(['upload_to_portal' => $uploadToPortal])->save();
    expect((bool) $export->fresh()->upload_to_portal)->toBe($uploadToPortal);
    $job = app(CompleteExport::class, [
        'export' => $export,
        'columnMap' => [],
        'formats' => [],
        'options' => ['upload_to_portal' => ! $uploadToPortal],
        'authGuard' => 'web',
    ]);

    unserialize(serialize($job))->handle();

    if ($uploadToPortal) {
        Queue::assertPushed(UploadDatasetRegister::class);
    } else {
        Queue::assertNotPushed(UploadDatasetRegister::class);
    }
})->with([true, false]);

it('streams CSV chunks in order without creating a register file on retries', function (): void {
    Storage::fake('local');
    Queue::fake([UploadDatasetRegister::class]);
    config(['services.open_data.base_url' => 'https://data.gov.ua', 'services.open_data.api_token' => 'test-token']);
    Http::preventStrayRequests();
    $first = str_repeat('a', 20000)."\n";
    Http::fake([
        'data.gov.ua/api/3/action/resource_patch' => function (Request $request) use ($first) {
            $body = $request->toPsrRequest()->getBody()->getContents();
            expect($body)->toContain("uid\n{$first}second\n")
                ->not->toContain('ignored')
                ->and($request->hasHeader('Content-Length'))->toBeFalse()
                ->and($request->header('Transfer-Encoding'))->toBe(['chunked']);

            return Http::response(['success' => true, 'result' => ['id' => 'register-resource']]);
        },
    ]);
    DatasetResource::factory()->create(['name' => DatasetResourceType::Register, 'external_id' => 'register-resource']);
    $export = new Export([
        'file_disk' => 'local',
        'exporter' => DatasetExporter::class,
        'total_rows' => 2,
        'processed_rows' => 2,
        'successful_rows' => 2,
        'user_id' => auth()->id(),
    ]);
    $export->options(['upload_to_portal' => true])->save();
    $directory = $export->getFileDirectory();
    $disk = Storage::disk('local');
    $disk->put($directory.'/headers.csv', "uid\n");
    $disk->put($directory.'/0000000000000001.csv', $first);
    $disk->put($directory.'/0000000000000002.csv', "second\n");
    $disk->put($directory.'/0000000000000003.csv', '');
    $disk->put($directory.'/ignored.txt', 'ignored');
    $disk->put($directory.'/otherheaders.csv', 'ignored');

    $export->touch('completed_at');

    Queue::assertPushed(UploadDatasetRegister::class);
    $job = Queue::pushed(UploadDatasetRegister::class)->first();
    $restoredJob = unserialize(serialize($job));
    $restoredJob->handle(app(OpenDataPortal::class));
    $restoredJob->handle(app(OpenDataPortal::class));

    $disk->assertMissing($directory.'/register.csv');
    expect(implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export))))->toBe("uid\n{$first}second\n");
    Http::assertSentCount(2);
});

it('does not prepare a register when exported records are incomplete', function (int $processedRows, int $successfulRows): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    Queue::fake([UploadDatasetRegister::class]);
    Log::spy();
    $export = new Export([
        'file_disk' => 'local',
        'exporter' => DatasetExporter::class,
        'total_rows' => 2,
        'processed_rows' => $processedRows,
        'successful_rows' => $successfulRows,
        'user_id' => auth()->id(),
    ]);
    $export->options(['upload_to_portal' => true])->save();

    $export->touch('completed_at');

    Storage::disk('local')->assertMissing($export->getFileDirectory().'/register.csv');
    Queue::assertNotPushed(UploadDatasetRegister::class);
})->with([[2, 1], [1, 1]]);

it('does not log updates that do not fill the completion timestamp', function (): void {
    Queue::fake([UploadDatasetRegister::class]);
    $export = new Export([
        'file_disk' => 'local',
        'exporter' => DatasetExporter::class,
        'total_rows' => 1,
        'user_id' => auth()->id(),
        'completed_at' => now()->subDay(),
    ]);
    $export->options(['upload_to_portal' => true])->save();
    Log::spy();

    $export->update(['file_name' => 'register']);
    $export->touch('completed_at');
    $export->update(['completed_at' => null]);

    Log::shouldNotHaveReceived('info');
    Queue::assertNotPushed(UploadDatasetRegister::class);
});

it('shows a concise completion message with exported and total row counts', function (): void {
    $export = new Export([
        'exporter' => DatasetExporter::class,
        'successful_rows' => 8,
        'total_rows' => 10,
    ]);

    expect(DatasetExporter::getCompletedNotificationBody($export))
        ->toBe('DatasetExporter - Успішно експортовано 8 із 10 записів.');
});

<?php

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Filament\Exports\OfficialExporter;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Models\Official;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('exports officials using the posts csv schema', function (): void {
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Коваль',
        'first_name' => 'Олена',
        'middle_name' => 'Вікторівна',
        'gender' => Gender::Female,
    ]);
    $official = Official::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create([
            'position' => 'Міський голова',
            'image' => 'https://example.com/photo.jpg',
            'homepage' => 'https://example.com/official',
        ]);
    $official->contactPoints()->createMany([
        ['type' => ContactPointType::Email, 'value' => 'public@example.gov.ua', 'is_public' => true],
        ['type' => ContactPointType::Email, 'value' => 'private@example.gov.ua', 'is_public' => false],
        ['type' => ContactPointType::Phone, 'value' => '+380501234567', 'is_public' => true],
        ['type' => ContactPointType::Phone, 'value' => '+380671234567', 'is_public' => true],
        ['type' => ContactPointType::Facebook, 'value' => 'https://facebook.com/koval', 'is_public' => true],
        ['type' => ContactPointType::Instagram, 'value' => 'https://instagram.com/koval', 'is_public' => true],
        ['type' => ContactPointType::Telegram, 'value' => 'https://t.me/koval', 'is_public' => false],
    ]);
    $official->reception()->create([
        'opening_hours' => 'Пн 09:00-12:00',
        'availability_restriction' => 'За попереднім записом',
    ]);
    $official->load([
        'person',
        'organization',
        'reception',
        'contactPoints' => fn (MorphMany $contactPoints): MorphMany => $contactPoints->orderBy('id'),
    ]);

    expect(OfficialExporter::test()->export($official))->toBe([
        'Коваль Олена Вікторівна',
        '2',
        'Міський голова',
        '04058410',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        'https://example.com/photo.jpg',
        'https://example.com/official',
        'https://facebook.com/koval, https://instagram.com/koval',
        "'+380501234567, +380671234567",
        'public@example.gov.ua',
        'null',
        'null',
        'Пн 09:00-12:00',
        'За попереднім записом',
    ]);

    expect(collect(OfficialExporter::getColumns())->map(
        fn (ExportColumn $column): ?string => $column->getLabel(),
    )->all())->toBe([
        'name',
        'gender',
        'positionName',
        'orgId',
        'orgName',
        'img',
        'homepage',
        'socialAccount',
        'telephone',
        'email',
        'unitId',
        'unitName',
        'openingHours',
        'availabilityRestriction',
    ]);
});

it('exports null optional values and unknown gender when the official has no additional details', function (): void {
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Коваль',
        'first_name' => 'Олена',
        'middle_name' => null,
        'gender' => null,
    ]);
    $official = Official::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create([
            'position' => 'Секретар',
            'image' => null,
            'homepage' => null,
        ]);
    $official->load(['person', 'organization', 'reception', 'contactPoints']);

    expect(OfficialExporter::test()->export($official))->toBe([
        'Коваль Олена',
        '0',
        'Секретар',
        '04058410',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        'null',
        'null',
        'null',
        'null',
        'null',
        'null',
        'null',
        'null',
        'null',
    ]);
});

it('offers the officials export as a posts csv action on the organizations page', function (): void {
    livewire(ListOrganizations::class)
        ->assertActionExists('exportOfficials', fn (ExportAction $action): bool => $action->getExporter() === OfficialExporter::class
            && $action->getFileName(new Export) === 'posts'
            && $action->getFormats() === [ExportFormat::Csv]
            && ! $action->hasColumnMapping());
});

it('exports officials from all organizations through the organizations page', function (): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    $first = Organization::factory()->create(['name' => 'Перша організація', 'edrpou' => '12345678']);
    $second = Organization::factory()->create(['name' => 'Друга організація', 'edrpou' => '87654321']);
    $firstPerson = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    $secondPerson = Person::factory()->create(['last_name' => 'Мельник', 'first_name' => 'Іван', 'middle_name' => null]);
    Official::factory()->for($firstPerson)->for($first, 'organization')->create(['position' => 'Директор']);
    Official::factory()->for($secondPerson)->for($second, 'organization')->create(['position' => 'Секретар']);

    livewire(ListOrganizations::class)
        ->searchTable('Перша організація')
        ->callAction('exportOfficials')
        ->assertNotified();

    $export = Export::query()->sole();
    expect($export->total_rows)->toBe(2)
        ->and($export->successful_rows)->toBe(2)
        ->and($export->completed_at)->not->toBeNull();

    $contents = implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export)));
    $csv = Reader::fromString($contents);
    $csv->setHeaderOffset(0);
    $rows = array_values(iterator_to_array($csv->getRecords()));

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'name'))->toBe(['Коваль Олена', 'Мельник Іван'])
        ->and(array_column($rows, 'orgId'))->toBe(['12345678', '87654321']);
});

it('continues to export organizations from the same page', function (): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    Organization::factory()->create(['name' => 'Перша організація', 'edrpou' => '12345678']);

    livewire(ListOrganizations::class)
        ->callAction('export')
        ->assertNotified();

    $export = Export::query()->sole();
    expect($export->total_rows)->toBe(1)
        ->and($export->successful_rows)->toBe(1)
        ->and($export->completed_at)->not->toBeNull();

    $contents = implode('', iterator_to_array(app(CsvExportContentGenerator::class)($export)));
    $csv = Reader::fromString($contents);
    $csv->setHeaderOffset(0);
    $rows = array_values(iterator_to_array($csv->getRecords()));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Id'])->toBe('12345678')
        ->and($rows[0]['Name'])->toBe('Перша організація');
});

<?php

use App\Enums\Gender;
use App\Filament\Exports\OfficialExporter;
use App\Filament\Imports\OrganizationImporter;
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
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
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
            'is_post' => true,
            'image' => 'https://example.com/photo.jpg',
            'homepage' => 'https://example.com/official',
            'email' => 'public@example.gov.ua',
            'phone' => '+380501234567',
            'social_accounts' => ['https://facebook.com/koval', 'https://instagram.com/koval'],
            'opening_hours' => 'Пн 09:00-12:00',
            'availability_restriction' => 'За попереднім записом',
        ]);
    $official->load(['person', 'organization']);

    expect(OfficialExporter::test()->export($official))->toBe([
        (string) $official->id,
        'Коваль Олена Вікторівна',
        '2',
        'Міський голова',
        'null',
        'null',
        '04058410',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
        'https://example.com/photo.jpg',
        'https://example.com/official',
        'https://facebook.com/koval, https://instagram.com/koval',
        '+380501234567',
        'public@example.gov.ua',
        'Пн 09:00-12:00',
        'За попереднім записом',
    ]);

    expect(collect(OfficialExporter::getColumns())->map(
        fn (ExportColumn $column): ?string => $column->getLabel(),
    )->all())->toBe([
        'uid',
        'name',
        'gender',
        'positionName',
        'unitId',
        'unitName',
        'orgId',
        'orgName',
        'img',
        'homepage',
        'socialAccount',
        'telephone',
        'email',
        'openingHours',
        'availabilityRestriction',
    ]);
});

it('exports null optional values and unknown gender when the official has no additional details', function (): void {
    $organization = Organization::factory()->create([
        'edrpou' => '04058410',
        'full_name' => 'ЗБОРІВСЬКА МІСЬКА РАДА',
    ]);
    $person = Person::factory()->create([
        'last_name' => 'Коваль',
        'first_name' => 'Олена',
        'middle_name' => null,
        'gender' => Gender::Unknown,
    ]);
    $official = Official::factory()
        ->for($person)
        ->for($organization, 'organization')
        ->create([
            'position' => 'Секретар',
            'is_post' => true,
            'image' => null,
            'homepage' => null,
        ]);
    $official->load(['person', 'organization']);

    expect(OfficialExporter::test()->export($official))->toBe([
        (string) $official->id,
        'Коваль Олена',
        '0',
        'Секретар',
        'null',
        'null',
        '04058410',
        'ЗБОРІВСЬКА МІСЬКА РАДА',
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
            && $action->getFormats() === [ExportFormat::Csv]);
});

it('exports officials from all organizations through the organizations page', function (): void {
    config(['queue.default' => 'sync']);
    Storage::fake('local');
    $first = Organization::factory()->create(['full_name' => 'Перша організація', 'edrpou' => '12345678']);
    $second = Organization::factory()->create(['full_name' => 'Друга організація', 'edrpou' => '87654321']);
    $firstPerson = Person::factory()->create(['last_name' => 'Коваль', 'first_name' => 'Олена', 'middle_name' => null]);
    $secondPerson = Person::factory()->create(['last_name' => 'Мельник', 'first_name' => 'Іван', 'middle_name' => null]);
    Official::factory()->for($firstPerson)->for($first, 'organization')->create(['position' => 'Директор', 'is_post' => true]);
    Official::factory()->for($secondPerson)->for($second, 'organization')->create(['position' => 'Секретар', 'is_post' => true]);
    $unlistedPerson = Person::factory()->create(['last_name' => 'Ткач', 'first_name' => 'Марія', 'middle_name' => null]);
    Official::factory()->for($unlistedPerson)->for($first, 'organization')->create([
        'position' => 'Не експортувати',
        'is_post' => false,
    ]);

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
    Organization::factory()->create([
        'full_name' => 'Перша організація',
        'edrpou' => '12345678',
        'social_accounts' => ['https://example.com/one', 'https://example.com/two'],
        'contact_point' => 'Приймальня',
        'email' => 'office@example.com',
        'phone' => '+380501234567',
        'opening_hours' => 'Пн 09:00-12:00',
        'availability_restriction' => 'За записом',
    ]);

    livewire(ListOrganizations::class)
        ->callAction('exportOrganizations')
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
        ->and($rows[0]['name'])->toBe('Перша організація')
        ->and($rows[0]['socialAccount'])->toBe('https://example.com/one, https://example.com/two')
        ->and($rows[0]['contactPointName'])->toBe('Приймальня')
        ->and($rows[0]['contactPointEmail'])->toBe('office@example.com')
        ->and($rows[0]['contactPointTelephone'])->toBe('+380501234567')
        ->and($rows[0]['openingHours'])->toBe('Пн 09:00-12:00')
        ->and($rows[0]['availabilityRestriction'])->toBe('За записом');

    OrganizationImporter::test(columnMap: [
        'edrpou' => 'Id',
        'name' => 'name',
        'description' => 'description',
        'email' => 'contactPointEmail',
        'phone' => 'contactPointTelephone',
    ])->import($rows[0])->assertImported();

    $organization = Organization::query()->where('edrpou', '12345678')->sole();
    expect($organization->email)->toBe('office@example.com')
        ->and($organization->phone)->toBe('+380501234567');
});

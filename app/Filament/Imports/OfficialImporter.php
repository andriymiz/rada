<?php

namespace App\Filament\Imports;

use App\Actions\StoreImportedContactPoints;
use App\Enums\Gender;
use App\Models\CouncilOrganization;
use App\Models\Official;
use App\Models\Person;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use LogicException;

class OfficialImporter extends Importer
{
    protected static ?string $model = Official::class;

    public function __construct(
        Import $import,
        array $columnMap,
        array $options,
        private readonly StoreImportedContactPoints $storeImportedContactPoints,
    ) {
        parent::__construct($import, $columnMap, $options);
    }

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('ПІБ')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('gender')
                ->label('Стать')
                ->requiredMapping()
                ->enum(Gender::class)
                ->rules(['required'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('positionName')
                ->label('Посада')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('orgId')
                ->label('Код організації')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:8'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('orgName')
                ->label('Назва організації')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('img')
                ->label('Фото')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048'])
                ->fillRecordUsing(function (Official $record, mixed $state): void {
                    $record->image = $state;
                }),

            ImportColumn::make('homepage')
                ->label('Домашня сторінка')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048']),

            ImportColumn::make('socialAccount')
                ->label('Соціальна мережа')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('telephone')
                ->label('Телефон')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->multiple(',')
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('email')
                ->label('Електронна пошта')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->multiple(',')
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['email'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('unitId')
                ->label('Код підрозділу')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('unitName')
                ->label('Назва підрозділу')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('openingHours')
                ->label('Графік прийому')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('availabilityRestriction')
                ->label('Обмеження доступності')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),
        ];
    }

    public function resolveRecord(): Official
    {
        $organization = $this->resolveOrganization();
        [$lastName, $firstName, $middleName] = ImportHelper::parseName($this->data['name'] ?? '');

        return Official::query()
            ->select('officials.*')
            ->join('people', 'people.id', '=', 'officials.person_id')
            ->where('people.last_name', $lastName)
            ->where('people.first_name', $firstName)
            ->where('people.middle_name', $middleName)
            ->whereBelongsTo($organization, 'organization')
            ->firstOrNew([], ['organization_id' => $organization->getKey()]);
    }

    protected function beforeSave(): void
    {
        $official = $this->getOfficialRecord();
        [$lastName, $firstName, $middleName] = ImportHelper::parseName($this->data['name'] ?? '');

        $person = $official->person ?? new Person;
        $person->last_name = $lastName;
        $person->first_name = $firstName;
        $person->middle_name = $middleName;
        $person->gender = $this->data['gender'] ?? null;
        $person->save();

        $official->person()->associate($person);
        $official->organization()->associate($this->resolveOrganization());
        $official->position = $this->data['positionName'] ?? '';
    }

    protected function afterSave(): void
    {
        $this->storeImportedContactPoints->handle(
            contactable: $this->getOfficialRecord(),
            emails: $this->data['email'] ?? [],
            telephones: $this->data['telephone'] ?? [],
            socialAccounts: $this->data['socialAccount'] ?? [],
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Імпорт посадовців завершено: успішно імпортовано '.Number::format($import->successful_rows).' '.trans_choice('{1} запис|[2,4] записи|[5,*] записів', $import->successful_rows).'.';

        if (($failedRowsCount = $import->getFailedRowsCount()) > 0) {
            $body .= ' Не вдалося імпортувати: '.Number::format($failedRowsCount).' '.trans_choice('{1} рядок|[2,4] рядки|[5,*] рядків', $failedRowsCount).'.';
        }

        return $body;
    }

    private function resolveOrganization(): CouncilOrganization
    {
        $organizationId = $this->data['orgId'] ?? null;
        $organizationName = $this->data['orgName'] ?? null;

        if (! is_string($organizationId) || ! is_string($organizationName) || blank($organizationId) || blank($organizationName)) {
            throw new LogicException('Ідентифікатор і назва організації є обов’язковими для імпорту посадовця.');
        }

        $organization = CouncilOrganization::query()
            ->where('edrpou', $organizationId)
            ->first();

        if (! $organization instanceof CouncilOrganization) {
            throw new LogicException("Організацію з кодом [{$organizationId}] не знайдено. Створіть її перед імпортом.");
        }

        return $organization;
    }

    private function getOfficialRecord(): Official
    {
        if (! $this->record instanceof Official) {
            throw new LogicException('Імпортер має працювати із записом посадовця.');
        }

        return $this->record;
    }
}

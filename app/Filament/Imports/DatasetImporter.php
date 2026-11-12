<?php

namespace App\Filament\Imports;

use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\Official;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class DatasetImporter extends Importer
{
    protected static ?string $model = Dataset::class;

    public function __construct(
        Import $import,
        array $columnMap,
        array $options,
    ) {
        parent::__construct($import, $columnMap, $options);
    }

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        $frequencyCases = collect(DatasetUpdateFrequency::cases());

        return [
            ImportColumn::make('external_id')
                ->label('Ідентифікатор набору')
                ->guess(['uid'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('title')
                ->label('Назва')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:500']),

            ImportColumn::make('description')
                ->label('Опис')
                ->requiredMapping()
                ->rules(['required', 'string']),

            ImportColumn::make('accrual_periodicity')
                ->label('Частота оновлення')
                ->guess(['accrualPeriodicity'])
                ->requiredMapping()
                ->rules(['required', Rule::in($frequencyCases->map->getLabel()->all())]),

            ImportColumn::make('keywords')
                ->label('Ключові слова')
                ->multiple(',')
                ->requiredMapping()
                ->rules(['required', 'array'])
                ->nestedRecursiveRules(['required', 'string']),

            ImportColumn::make('purpose')
                ->label('Підстава та призначення збору інформації')
                ->requiredMapping()
                ->rules(['required', 'string']),

            ImportColumn::make('distribution_formats')
                ->label('Формати ресурсів')
                ->guess(['distributionFormat'])
                ->multiple(',')
                ->requiredMapping()
                ->rules(['required', 'array'])
                ->nestedRecursiveRules(['required', Rule::enum(DatasetResourceFormat::class)]),

            ImportColumn::make('publisher_id')
                ->label('Ідентифікатор розпорядника')
                ->guess(['publisherId'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:8'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('publisher_name')
                ->label('Назва розпорядника')
                ->guess(['publisherName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('contact_point_name')
                ->label('Відповідальна особа (ПІБ)')
                ->guess(['contactPointName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->helperText('Електронна пошта контактної особи ігнорується; Якщо особа за ПІБ не знайдена, запис буде пропущено.')
                ->fillRecordUsing(fn (): null => null),
        ];
    }

    public function resolveRecord(): Dataset
    {
        $organization = $this->resolveOrganization();
        $externalId = $this->data['external_id'] ?? null;

        if (is_string($externalId) && Str::isUuid($externalId)) {
            $dataset = Dataset::query()->where('external_id', $externalId)->first();

            if ($dataset instanceof Dataset) {
                if ($dataset->publisher_id !== $organization->getKey()) {
                    throw new RowImportFailedException("Набір даних з ідентифікатором [{$externalId}] уже належить іншому розпоряднику.");
                }

                return $dataset;
            }
        }

        $dataset = Dataset::query()->firstOrNew([
            'title' => $this->data['title'],
            'publisher_id' => $organization->getKey(),
        ]);

        if (! $dataset->exists && is_string($externalId) && Str::isUuid($externalId)) {
            $dataset->external_id = $externalId;
        }

        return $dataset;
    }

    protected function beforeSave(): void
    {
        $organization = $this->resolveOrganization();

        $this->getDatasetRecord()
            ->publisher()
            ->associate($organization);

        $this->getDatasetRecord()
            ->responsibleOfficial()
            ->associate($this->resolveResponsibleOfficial($organization));
    }

    protected function afterValidate(): void
    {
        $frequencyLabel = $this->data['accrual_periodicity'];
        $this->data['accrual_periodicity'] = collect(DatasetUpdateFrequency::cases())
            ->mapWithKeys(fn (DatasetUpdateFrequency $frequency): array => [
                $frequency->getLabel() => $frequency->value,
            ])
            ->get($frequencyLabel);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }

    private function resolveOrganization(): CouncilOrganization
    {
        $organizationId = $this->data['publisher_id'] ?? null;
        $organizationName = $this->data['publisher_name'] ?? null;

        if (! is_string($organizationName) || blank($organizationName)) {
            throw new RowImportFailedException('Назва розпорядника є обов’язковою для імпорту набору даних.');
        }

        $organization = self::findOrganization($this->data);

        if (! $organization instanceof CouncilOrganization) {
            $identifier = is_string($organizationId) && filled($organizationId)
                ? " з кодом [{$organizationId}]"
                : " з назвою [{$organizationName}]";

            throw new RowImportFailedException("Організацію{$identifier} не знайдено. Створіть її перед імпортом.");
        }

        return $organization;
    }

    private function resolveResponsibleOfficial(CouncilOrganization $organization): Official
    {
        // Contact-point email is ignored; the official is resolved from the responsible person's name.
        $contactName = (string) ($this->data['contact_point_name'] ?? '');

        return self::findResponsibleOfficial($organization, $contactName);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function findOrganization(array $data): ?CouncilOrganization
    {
        $organizationId = $data['publisher_id'] ?? null;
        $organizationName = $data['publisher_name'] ?? null;

        if (! is_string($organizationName) || blank($organizationName)) {
            return null;
        }

        $query = CouncilOrganization::query();

        if (is_string($organizationId) && filled($organizationId)) {
            $query->where('edrpou', $organizationId);
        } else {
            $query->where('name', $organizationName);
        }

        return $query->first();
    }

    private static function findResponsibleOfficial(CouncilOrganization $organization, string $contactName): Official
    {
        [$lastName, $firstName, $middleName] = ImportHelper::parseName($contactName);

        try {
            return Official::query()
                ->whereBelongsTo($organization, 'organization')
                ->whereHas('person', fn ($query) => $query
                    ->where('last_name', $lastName)
                    ->where('first_name', $firstName)
                    ->where('middle_name', $middleName))
                ->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            throw new RowImportFailedException(
                "Відповідальну особу [{$contactName}] не знайдено серед посадовців організації [{$organization->name}].",
                previous: $exception,
            );
        }
    }

    private function getDatasetRecord(): Dataset
    {
        if (! $this->record instanceof Dataset) {
            throw new LogicException('Імпортер має працювати із записом набору даних.');
        }

        return $this->record;
    }
}

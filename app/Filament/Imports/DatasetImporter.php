<?php

namespace App\Filament\Imports;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetUpdateFrequency;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class DatasetImporter extends Importer
{
    protected static ?string $model = Dataset::class;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        $frequencyCases = collect(DatasetUpdateFrequency::cases());

        return [
            ImportColumn::make('external_id')
                ->label('Ідентифікатор набору')
                ->guesses(['uid'])
                ->requiredMapping()
                ->rules(['required', 'uuid']),

            ImportColumn::make('title')
                ->label('Назва')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:500'])
                ->fillRecordUsing(function (Dataset $record, string $state): void {
                    if (! $record->exists || blank($record->title)) {
                        $record->title = $state;
                    }
                }),

            ImportColumn::make('description')
                ->label('Опис')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('accrual_periodicity')
                ->label('Частота оновлення')
                ->guesses(['accrualPeriodicity'])
                ->requiredMapping()
                ->rules(['required', Rule::in($frequencyCases->map->getLabel()->all())])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('keywords')
                ->label('Ключові слова')
                ->multiple(',')
                ->requiredMapping()
                ->rules(['required', 'array'])
                ->nestedRecursiveRules(['required', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('purpose')
                ->label('Підстава та призначення збору інформації')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('publisher_id')
                ->label('Ідентифікатор розпорядника')
                ->guesses(['publisherId'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:8'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('publisher')
                ->label('Назва розпорядника')
                ->guesses(['publisherName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->relationship(resolveUsing: function (string $state, array $data, Dataset $record): ?CouncilOrganization {
                    $organizationId = $data['publisher_id'] ?? null;
                    $organization = is_string($organizationId) && filled($organizationId)
                        ? CouncilOrganization::query()->where('edrpou', $organizationId)->first()
                        : CouncilOrganization::query()->where('name', $state)->first();

                    if (
                        $organization instanceof CouncilOrganization
                        && $record->exists
                        && $record->publisher_id !== $organization->getKey()
                    ) {
                        $identifier = filled($record->external_id)
                            ? "з ідентифікатором [{$record->external_id}]"
                            : "з офіційною назвою [{$record->title}]";

                        throw new RowImportFailedException(
                            "Набір даних {$identifier} уже належить іншому розпоряднику.",
                        );
                    }

                    return $organization;
                }),

            ImportColumn::make('contact_point_name')
                ->label('Відповідальна особа (ПІБ)')
                ->guesses(['contactPointName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->helperText('Зберігається в метаданих набору без зв’язку з посадовцем у системі.')
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('contact_point_email')
                ->label('Електронна пошта контактної особи')
                ->guess(['contactPointEmail'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
        ];
    }

    public function resolveRecord(): Dataset
    {
        $externalId = $this->data['external_id'] ?? null;

        if (is_string($externalId) && Str::isUuid($externalId)) {
            $dataset = Dataset::query()->where('external_id', $externalId)->first();

            if ($dataset instanceof Dataset) {
                return $dataset;
            }
        }

        $officialTitle = $this->data['title'] ?? null;
        $recommendation = is_string($officialTitle)
            ? collect(DatasetRecommendation::cases())
                ->first(fn (DatasetRecommendation $case): bool => $case->getLabel() === $officialTitle)
            : null;
        $dataset = $recommendation instanceof DatasetRecommendation
            ? Dataset::query()->where('rec_code', $recommendation->value)->first()
            : null;

        if (
            ! $dataset instanceof Dataset
            && is_string($officialTitle)
            && filled($officialTitle)
        ) {
            $dataset = Dataset::query()->where('title', $officialTitle)->first();
        }

        return $dataset ?? new Dataset;
    }

    protected function afterSave(): void
    {
        $this->getDatasetRecord()
            ->meta()
            ->updateOrCreate([], [
                'description' => $this->data['description'],
                'accrual_periodicity' => $this->data['accrual_periodicity'],
                'keywords' => $this->data['keywords'],
                'purpose' => $this->data['purpose'],
                'contact_point_name' => $this->data['contact_point_name'],
                'contact_point_email' => $this->data['contact_point_email'],
            ]);
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

    private function getDatasetRecord(): Dataset
    {
        if (! $this->record instanceof Dataset) {
            throw new LogicException('Імпортер має працювати із записом набору даних.');
        }

        return $this->record;
    }
}

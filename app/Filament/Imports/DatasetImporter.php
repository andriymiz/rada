<?php

namespace App\Filament\Imports;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\DatasetUpdateFrequency;
use App\Models\Dataset;
use App\Models\Organization;
use App\Services\OpenDataPortal;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;

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
                ->fillRecordUsing(function (DatasetImporter $importer, string $state): void {
                    $importer->data['accrual_periodicity'] = collect(DatasetUpdateFrequency::cases())
                        ->mapWithKeys(fn (DatasetUpdateFrequency $frequency): array => [
                            $frequency->getLabel() => $frequency->value,
                        ])
                        ->get($state);
                }),

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
                ->relationship(resolveUsing: function (string $state, array $data, Dataset $record): ?Organization {
                    return Organization::query()
                        // ->where('full_name', $state)
                        ->when($data['publisher_id'] ?? null,
                            fn ($query, $organizationId) => $query->where('edrpou', $organizationId))
                        ->first();
                }),

            ImportColumn::make('contact_point_name')
                ->label('Відповідальна особа (ПІБ)')
                ->guesses(['contactPointName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
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
        // Find by external ID first
        $dataset = Dataset::query()->where('external_id', $this->data['external_id'])->first();
        if ($dataset instanceof Dataset) {
            return $dataset;
        }

        // If not found by external ID, try finding by recommendation code
        $title = $this->data['title'] ?? null;
        $recommendation = collect(DatasetRecommendation::cases())
            ->first(fn (DatasetRecommendation $case): bool => $case->getLabel() === $title);
        $dataset = Dataset::query()->where('rec_code', $recommendation)->first();
        if ($recommendation instanceof DatasetRecommendation && $dataset instanceof Dataset) {
            return $dataset;
        }

        // If not found by external ID or recommendation code, try finding by title
        return Dataset::query()->where('title', $title)->first() ?? new Dataset;
    }

    protected function beforeSave(): void
    {
        /** @var Dataset $dataset */
        $dataset = $this->record;

        $dataset->status = DatasetStatus::Published;
    }

    protected function afterSave(): void
    {
        /** @var Dataset $dataset */
        $dataset = $this->record;

        $dataset->meta()
            ->updateOrCreate([], [
                'description' => $this->data['description'],
                'accrual_periodicity' => $this->data['accrual_periodicity'],
                'keywords' => $this->data['keywords'],
                'purpose' => $this->data['purpose'],
                'contact_point_name' => $this->data['contact_point_name'],
                'contact_point_email' => $this->data['contact_point_email'],
            ]);

        try {
            app(OpenDataPortal::class)->syncDatasetResources($dataset);
        } catch (RequestException $exception) {
            report($exception);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }
}

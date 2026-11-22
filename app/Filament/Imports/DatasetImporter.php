<?php

namespace App\Filament\Imports;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\DatasetUpdateFrequency;
use App\Models\Dataset;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
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
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->requiredMapping()
                ->rules(['nullable', 'uuid']),

            ImportColumn::make('external_title')
                ->label('Повна Назва')
                ->guesses(['title'])
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
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'required_with:external_id', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('accrual_periodicity')
                ->label('Частота оновлення')
                ->guesses(['accrualPeriodicity'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->requiredMapping()
                ->rules(['nullable', 'required_with:external_id', Rule::in($frequencyCases->map->getLabel()->all())])
                ->fillRecordUsing(function (DatasetImporter $importer, ?string $state): void {
                    $importer->data['accrual_periodicity'] = filled($state)
                        ? collect(DatasetUpdateFrequency::cases())
                            ->mapWithKeys(fn (DatasetUpdateFrequency $frequency): array => [
                                $frequency->getLabel() => $frequency->value,
                            ])
                            ->get($state)
                        : null;
                }),

            ImportColumn::make('keywords')
                ->label('Ключові слова')
                ->multiple(',')
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'required_with:external_id', 'array'])
                ->nestedRecursiveRules(['required', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('purpose')
                ->label('Підстава та призначення збору інформації')
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'required_with:external_id', 'string'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('publisher')
                ->label('Ідентифікатор розпорядника')
                ->guesses(['publisherId'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:8'])
                ->relationship(resolveUsing: 'edrpou'),

            ImportColumn::make('contact_point_name')
                ->label('Відповідальна особа (ПІБ)')
                ->guesses(['contactPointName'])
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'required_with:external_id', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('contact_point_email')
                ->label('Електронна пошта контактної особи')
                ->guesses(['contactPointEmail'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->requiredMapping()
                ->rules(['nullable', 'required_with:external_id', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('internal_title')
                ->label('Внутрішня назва')
                ->guesses(['internalTitle'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:500'])
                ->fillRecordUsing(function (Dataset $record, mixed $state): void {
                    if (is_string($state) && filled($state)) {
                        $record->title = $state;
                    }
                }),

            ImportColumn::make('status')
                ->label('Статус')
                ->guesses(['status'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', Rule::enum(DatasetStatus::class)]),

            ImportColumn::make('status_note')
                ->label('Примітка до статусу')
                ->guesses(['statusNote'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string']),

            ImportColumn::make('info_owner')
                ->label('Внутрішній розпорядник')
                ->guesses(['infoOwner'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    public function resolveRecord(): Dataset
    {
        if (filled($externalId = $this->data['external_id'] ?? null)) {
            $dataset = Dataset::query()->where('external_id', $externalId)->first();
            if ($dataset instanceof Dataset) {
                return $dataset;
            }
        }

        // If not found by external ID, try finding by recommendation code
        $title = $this->data['external_title'] ?? null;
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

        $status = $this->data['status'] ?? null;
        $dataset->status = DatasetStatus::tryFrom($status) ?? DatasetStatus::Published;
    }

    protected function afterSave(): void
    {
        if (blank($this->data['external_id'] ?? null)) {
            return;
        }

        /** @var Dataset $dataset */
        $dataset = $this->record;

        $dataset->meta()->updateOrCreate([], [
            'description' => $this->data['description'],
            'accrual_periodicity' => $this->data['accrual_periodicity'],
            'keywords' => $this->data['keywords'],
            'purpose' => $this->data['purpose'],
            'contact_point_name' => $this->data['contact_point_name'],
            'contact_point_email' => $this->data['contact_point_email'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }
}

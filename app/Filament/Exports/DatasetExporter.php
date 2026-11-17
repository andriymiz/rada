<?php

namespace App\Filament\Exports;

use App\Enums\DatasetUpdateFrequency;
use App\Models\Dataset;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Checkbox;
use Illuminate\Database\Eloquent\Builder;

class DatasetExporter extends Exporter
{
    protected static ?string $model = Dataset::class;

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('upload_to_portal')
                ->label('Автоматично завантажити register.csv на портал відкритих даних'),
        ];
    }

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('external_id')->label('uid'),
            ExportColumn::make('title')
                ->label('title')
                ->state(fn (Dataset $record): string => $record->rec_code?->getLabel() ?? $record->title),
            ExportColumn::make('meta.description')->label('description'),
            ExportColumn::make('accrualPeriodicity')
                ->label('accrualPeriodicity')
                ->state(fn (Dataset $record): string => self::formatUpdateFrequency(
                    $record->meta->accrual_periodicity,
                )),
            ExportColumn::make('meta.keywords')->label('keywords'),
            ExportColumn::make('meta.purpose')->label('purpose'),
            ExportColumn::make('url')->label('url'),
            ExportColumn::make('distributionFormat')
                ->label('distributionFormat')
                ->state(fn (Dataset $record): string => $record->resources
                    ->pluck('format')
                    ->filter(fn (mixed $format): bool => is_string($format) && filled($format))
                    ->unique()
                    ->implode(', ')),
            ExportColumn::make('publisher.name')->label('publisherName'),
            ExportColumn::make('publisher.edrpou')->label('publisherId'),
            ExportColumn::make('meta.contact_point_name')->label('contactPointName'),
            ExportColumn::make('meta.contact_point_email')->label('contactPointEmail'),
        ];
    }

    /**
     * @param  Builder<Dataset>  $query
     * @return Builder<Dataset>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['meta', 'publisher', 'resources']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }

    private static function formatUpdateFrequency(string $value): string
    {
        $frequency = collect(DatasetUpdateFrequency::cases())
            ->first(fn (DatasetUpdateFrequency $case): bool => in_array($value, [
                $case->value,
                $case->getLabel(),
            ], true));

        return $frequency?->getLabel() ?? $value;
    }
}

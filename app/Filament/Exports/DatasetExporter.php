<?php

namespace App\Filament\Exports;

use App\Enums\ContactPointType;
use App\Models\Dataset;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DatasetExporter extends Exporter
{
    protected static ?string $model = Dataset::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uid')
                ->label('uid')
                ->state(fn (Dataset $record): string => $record->external_id),
            ExportColumn::make('title')
                ->label('title')
                ->formatStateUsing(fn (?string $state): string => self::formatCsvText($state)),
            ExportColumn::make('description')
                ->label('description')
                ->formatStateUsing(fn (?string $state): string => self::formatCsvText($state)),
            ExportColumn::make('accrualPeriodicity')
                ->label('accrualPeriodicity')
                ->state(fn (Dataset $record): string => $record->accrual_periodicity->getLabel()),
            ExportColumn::make('keywords')
                ->label('keywords')
                ->state(fn (Dataset $record): string => implode(', ', $record->keywords))
                ->formatStateUsing(fn (?string $state): string => self::formatCsvText($state)),
            ExportColumn::make('purpose')
                ->label('purpose')
                ->formatStateUsing(fn (?string $state): string => self::formatCsvText($state)),
            ExportColumn::make('url')
                ->label('url')
                ->state(fn (Dataset $record): string => $record->url),
            ExportColumn::make('distributionFormat')
                ->label('distributionFormat')
                ->state(fn (Dataset $record): string => implode(', ', $record->distribution_formats)),
            ExportColumn::make('publisherName')
                ->label('publisherName')
                ->state(fn (Dataset $record): string => $record->publisher->name),
            ExportColumn::make('publisherId')
                ->label('publisherId')
                ->state(fn (Dataset $record): string => $record->publisher->edrpou ?? 'null')
                ->formatStateUsing(fn (?string $state): string => self::formatCsvText($state)),
            ExportColumn::make('contactPointName')
                ->label('contactPointName')
                ->state(fn (Dataset $record): string => $record->responsibleOfficial->person->full_name),
            ExportColumn::make('contactPointEmail')
                ->label('contactPointEmail')
                ->state(fn (Dataset $record): string => $record->responsibleOfficial->contactPoints
                    ->firstWhere('type', ContactPointType::Email)
                    ->value ?? 'null'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }

    private static function formatCsvText(?string $value): string
    {
        if (filled($value) && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'{$value}";
        }

        return $value ?? '';
    }
}

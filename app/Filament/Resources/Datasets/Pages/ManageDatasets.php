<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Exports\DatasetExporter;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Widgets\DatasetStatsOverview;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageDatasets extends ManageRecords
{
    protected static string $resource = DatasetResource::class;

    public const PUBLICATION_RECOMMENDATIONS_URL = 'https://data.gov.ua/pages/835-rec-index';

    protected function getHeaderWidgets(): array
    {
        return [
            DatasetStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('helpPublicationRecommendations')
                    ->label('Рекомендації')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(self::PUBLICATION_RECOMMENDATIONS_URL)
                    ->openUrlInNewTab(),
            ])
                ->label('Довідка')
                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->color('gray')
                ->button(),
            ActionGroup::make([
                ImportAction::make()
                    ->label('Імпорт наборів')
                    ->pluralModelLabel('наборів даних')
                    ->importer(DatasetImporter::class),
                ExportAction::make()
                    ->label('Експорт наборів')
                    ->pluralModelLabel('наборів даних')
                    ->exporter(DatasetExporter::class)
                    ->fileName('register')
                    ->formats([ExportFormat::Csv])
                    ->modifyQueryUsing(fn (Builder $query, array $options): Builder => $query
                        ->when($options['upload_to_portal'] ?? false, fn (Builder $query): Builder => $query
                            ->whereNotNull('external_id')
                            ->whereHas('meta')
                        )->with(['meta', 'publisher'])
                        ->reorder()
                        ->orderBy('group_id')
                        ->orderBy('sort')),
            ])
                ->label('Функції')
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }
}

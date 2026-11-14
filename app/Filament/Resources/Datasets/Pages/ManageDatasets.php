<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Enums\DatasetResourceType;
use App\Filament\Exports\DatasetExporter;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Imports\ImportHelper;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Models\Dataset;
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
                    ->label('Імпорт з CSV')
                    ->pluralModelLabel('наборів даних')
                    ->importer(DatasetImporter::class)
                    ->registerModalActions([
                        ImportHelper::downloadPortalResourceAction(DatasetResourceType::Register),
                    ]),
                ExportAction::make()
                    ->label('Експорт в CSV')
                    ->pluralModelLabel('наборів даних')
                    ->exporter(DatasetExporter::class)
                    ->fileName('register')
                    ->columnMapping(false)
                    ->formats([ExportFormat::Csv])
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query
                        ->whereNotNull((new Dataset)->qualifyColumn('external_id'))
                        ->whereHas('meta')
                        ->with([
                            'meta',
                            'publisher',
                        ])),
            ])
                ->label('Функції')
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }
}

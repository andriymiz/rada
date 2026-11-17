<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Exports\OfficialExporter;
use App\Filament\Exports\OrganizationExporter;
use App\Filament\Imports\OfficialImporter;
use App\Filament\Imports\OrganizationImporter;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Official;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrganizations extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ActionGroup::make([
                    ImportAction::make('importOrganizations')
                        ->label('Імпорт організацій')
                        ->pluralModelLabel('організацій')
                        ->importer(OrganizationImporter::class),
                    ExportAction::make('exportOrganizations')
                        ->label('Експорт організацій')
                        ->pluralModelLabel('організацій')
                        ->exporter(OrganizationExporter::class)
                        ->fileName('organizations')
                        ->columnMapping(false)
                        ->formats([ExportFormat::Csv])
                        ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                            'head.person',
                            'parentOrganization',
                        ])),
                ])->dropdown(false),
                ActionGroup::make([
                    ImportAction::make('importOfficials')
                        ->label('Імпорт посадовців')
                        ->pluralModelLabel('посадовців')
                        ->importer(OfficialImporter::class),
                    ExportAction::make('exportOfficials')
                        ->label('Експорт посадовців')
                        ->pluralModelLabel('посадовців')
                        ->exporter(OfficialExporter::class)
                        ->fileName('posts')
                        ->columnMapping(false)
                        ->formats([ExportFormat::Csv])
                        ->modifyQueryUsing(fn (): Builder => OfficialExporter::modifyQuery(Official::query())),
                ])->dropdown(false),
            ])
                ->label('Функції')
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }
}

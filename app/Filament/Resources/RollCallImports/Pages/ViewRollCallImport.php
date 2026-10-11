<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/** @extends ViewRecord<RollCallImport> */
class ViewRollCallImport extends ViewRecord
{
    protected static string $resource = RollCallImportResource::class;

    protected string $view = 'filament.resources.roll-call-imports.pages.view-roll-call-import';

    public function getTitle(): string
    {
        return "Результати імпорту: {$this->getRecord()->original_filename}";
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            PlenaryMeetingResource::getUrl() => 'Засідання',
            '' => 'Імпорти',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPdf')
                ->label('Переглянути PDF')
                ->url(fn (): string => route('roll-call-imports.pdf', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}

<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Jobs\ProcessRollCallImport;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRollCallImports extends ListRecords
{
    protected static string $resource = RollCallImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Новий імпорт')
                ->schema([RollCallImportForm::fileUpload()])
                ->action(function (array $data): void {
                    foreach ($data['files'] as $filePath) {
                        $import = RollCallImport::query()->create([
                            'user_id' => auth()->id(),
                            'file_path' => $filePath,
                            'original_filename' => $data['original_filenames'][$filePath] ?? basename($filePath),
                        ]);

                        ProcessRollCallImport::dispatch($import);
                    }

                    Notification::make()
                        ->title('Файли додано до черги обробки')
                        ->success()
                        ->send();
                }),
        ];
    }
}

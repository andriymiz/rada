<?php

namespace App\Filament\Resources\RollCallImports\Schemas;

use App\Filament\Resources\PlenaryMeetings\Schemas\PlenaryMeetingForm;
use App\Models\PlenaryMeeting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class RollCallImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('plenary_meeting_id')
                    ->label('Засідання')
                    ->options(fn (): array => PlenaryMeeting::query()
                        ->with(['organization', 'parliamentarySession.convocation'])
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (PlenaryMeeting $meeting): array => [
                            $meeting->id => $meeting->displayName(),
                        ])
                        ->all())
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->createOptionForm(PlenaryMeetingForm::components())
                    ->createOptionUsing(fn (array $data): int => (int) PlenaryMeeting::query()->create($data)->getKey())
                    ->createOptionModalHeading('Нове пленарне засідання'),
            ]);
    }

    public static function fileUpload(): FileUpload
    {
        return FileUpload::make('files')
            ->label('PDF-файл(и) із поіменними голосуваннями')
            ->disk('local')
            ->directory('roll-call-imports')
            ->visibility('private')
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(20480)
            ->multiple()
            ->storeFileNamesIn('original_filenames')
            ->required();
    }
}

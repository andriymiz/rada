<?php

namespace App\Filament\Resources\RollCallImports\Schemas;

use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RollCallImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('convocation_id')
                    ->label('Скликання')
                    ->options(fn (): array => ParliamentaryConvocation::query()
                        ->orderBy('id')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('session_id', null))
                    ->native(false),
                Select::make('session_id')
                    ->label('Сесія')
                    ->options(fn (Get $get): array => filled($get('convocation_id'))
                        ? ParliamentarySession::query()
                            ->where('convocation_id', $get('convocation_id'))
                            ->orderBy('id')
                            ->pluck('name', 'id')
                            ->all()
                        : [])
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('convocation_id')))
                    ->native(false),
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

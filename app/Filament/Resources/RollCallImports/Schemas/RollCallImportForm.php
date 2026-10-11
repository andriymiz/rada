<?php

namespace App\Filament\Resources\RollCallImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class RollCallImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function fileUpload(bool $required = false): FileUpload
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
            ->required($required);
    }
}

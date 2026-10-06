<?php

namespace App\Filament\Resources\RollCallImports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RollCallImportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('original_filename')
                    ->label('Файл'),
                TextEntry::make('session.convocation.name')
                    ->label('Скликання'),
                TextEntry::make('session.name')
                    ->label('Сесія'),
                TextEntry::make('status')
                    ->label('Статус')
                    ->badge(),
                TextEntry::make('created_at')
                    ->label('Дата завантаження')
                    ->dateTime(),
            ]);
    }
}

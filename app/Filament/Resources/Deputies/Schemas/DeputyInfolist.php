<?php

namespace App\Filament\Resources\Deputies\Schemas;

use App\Enums\Gender;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DeputyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('full_name')
                    ->label('ПІБ'),
                TextEntry::make('person.date_of_birth')
                    ->label('Дата народження')
                    ->date(),
                TextEntry::make('person.gender')
                    ->label('Стать')
                    ->formatStateUsing(fn (?Gender $state): string => $state?->getLabel() ?? '—'),
                TextEntry::make('homepage')
                    ->label('Сторінка на сайті ради')
                    ->placeholder('—'),
                TextEntry::make('reception.address_post_name')
                    ->label('Населений пункт прийому')
                    ->placeholder('—'),
                TextEntry::make('reception.opening_hours')
                    ->label('Графік прийому')
                    ->placeholder('—'),
                TextEntry::make('biography')
                    ->label('Біографія')
                    ->columnSpanFull(),
            ]);
    }
}

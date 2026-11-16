<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Models\Organization;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->length(8)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('name')
                    ->label('Повна назва за ЄДР')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_name')
                    ->label('Коротка назва')
                    ->required()
                    ->maxLength(255),
                TextInput::make('katoottg')
                    ->label('КАТОТТГ')
                    ->length(19)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Опис')
                    ->maxLength(300)
                    ->columnSpanFull(),
                Select::make('parent_org_id')
                    ->label('Належність до сфери управління')
                    ->relationship(
                        'parentOrganization',
                        'name',
                        modifyQueryUsing: fn (Builder $query, ?Organization $record): Builder => $query
                            ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey())),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('constituent_doc')
                    ->label('Установчий документ')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                TextInput::make('homepage')
                    ->label('Офіційний веб-сайт')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                Repeater::make('social_accounts')
                    ->simple(
                        TextInput::make('url')
                            ->url()
                            ->rule('url:http,https')
                            ->required(),
                    )
                    ->columnSpanFull(),
                TextInput::make('logo')
                    ->label('Логотип')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048)
                    ->columnSpanFull(),
            ]);
    }
}

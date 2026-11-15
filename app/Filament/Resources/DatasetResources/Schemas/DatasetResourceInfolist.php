<?php

namespace App\Filament\Resources\DatasetResources\Schemas;

use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DatasetResourceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $modalState = fn (Component $component): mixed => data_get($component->getLivewire(), $component->getStatePath());

        return $schema->components([
            TextEntry::make('description')
                ->label('Опис')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('datastore_status')->label('Стан сховище даних')->state($modalState),
            TextEntry::make('total')->label('Кількість записів')->state($modalState)->placeholder('—'),
            RepeatableEntry::make('fields')
                ->columnSpanFull()
                ->label('Поля')
                ->state($modalState)
                ->table([
                    TableColumn::make('Назва'),
                    TableColumn::make('Тип'),
                    TableColumn::make('Мітка'),
                ])
                ->schema([
                    TextEntry::make('id')->label('Назва'),
                    TextEntry::make('type')->label('Тип'),
                    TextEntry::make('info.label')->label('Мітка')->placeholder('—'),
                ]),
            RepeatableEntry::make('views')
                ->columnSpanFull()
                ->label('Вигляди')
                ->state($modalState)
                ->table([
                    TableColumn::make('Назва'),
                    TableColumn::make('Тип'),
                    TableColumn::make('Опис'),
                    TableColumn::make('Перегляд')->hiddenHeaderLabel(),
                ])
                ->schema([
                    TextEntry::make('title')->label('Назва'),
                    TextEntry::make('view_type')->label('Тип'),
                    TextEntry::make('description')->label('Опис')->placeholder('—'),
                    Actions::make([
                        Action::make('viewResourceData')
                            ->label('Переглянути на порталі')
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->iconButton()
                            ->url(function (Get $get, OpenDataPortal $portal): string {
                                $viewId = $get('id');
                                $resourceId = $get('resource_id');
                                $packageId = $get('package_id');

                                return $portal->getResourceViewUrl($packageId, $resourceId, $viewId);
                            })
                            ->openUrlInNewTab(),
                    ]),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function loadResourceDetails(array $data, DatasetResource $record, OpenDataPortal $portal): array
    {
        return array_merge($data, $portal->getResourceDetails($record->external_id));
    }
}

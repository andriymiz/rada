<?php

namespace App\Filament\Resources\Datasets;

use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\Dataset;
use App\Models\Official;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'набір даних';

    protected static ?string $pluralModelLabel = 'Набори даних';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Назва')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Опис')
                    ->required()
                    ->columnSpanFull(),
                Select::make('accrual_periodicity')
                    ->label('Частота оновлення')
                    ->options(DatasetUpdateFrequency::class)
                    ->required(),
                Select::make('distribution_formats')
                    ->label('Формати ресурсів')
                    ->options(DatasetResourceFormat::class)
                    ->multiple()
                    ->searchable()
                    ->required(),
                TagsInput::make('keywords')
                    ->label('Ключові слова')
                    ->placeholder('Введіть слово та натисніть Enter')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('purpose')
                    ->label('Підстава та призначення збору інформації')
                    ->required()
                    ->columnSpanFull(),
                Select::make('publisher_id')
                    ->label('Розпорядник')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('responsible_official_id')
                    ->label('Відповідальна особа')
                    ->relationship(
                        'responsibleOfficial',
                        'position',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->with(['person', 'contactPoints']),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Official $record): string => $record->person->short_name)
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('external_id')
                    ->label('Ідентифікатор')
                    ->limit(14)
                    ->copyable()
                    ->copyableState(fn (string $state): string => $state),
                TextColumn::make('title')
                    ->label('Назва')
                    ->limit(50),
                TextColumn::make('accrual_periodicity')
                    ->label('Частота оновлення')
                    ->formatStateUsing(fn (DatasetUpdateFrequency $state): string => $state->getLabel()),
                TextColumn::make('publisher.name')
                    ->label('Розпорядник'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'responsibleOfficial.person',
                'responsibleOfficial.contactPoints',
                'publisher',
            ]))
            ->recordActions([
                Action::make('portalLink')
                    ->label('Відкрити на порталі')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->url(fn (Dataset $record): string => $record->url)
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ResourcesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDatasets::route('/'),
            'edit' => EditDataset::route('/{record}/edit'),
        ];
    }
}

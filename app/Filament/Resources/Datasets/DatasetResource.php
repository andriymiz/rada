<?php

namespace App\Filament\Resources\Datasets;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\Dataset;
use App\Models\DatasetGroup;
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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

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
                Select::make('rec_code')
                    ->label('Набір даних із переліку')
                    ->options(DatasetRecommendation::class)
                    ->searchable()
                    ->rule(Rule::enum(DatasetRecommendation::class))
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('Назва')
                    ->required()
                    ->maxLength(500),
                Select::make('group_id')
                    ->label('Група')
                    ->relationship('group', 'name')
                    ->preload()
                    ->createOptionForm(static fn (): array => self::groupOptionForm())
                    ->editOptionForm(static fn (): array => self::groupOptionForm()),
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

    /**
     * @return array<int, TextInput>
     */
    private static function groupOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label('Назва групи')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('sort')
                ->label('Порядок')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->default(fn (): int => ((int) DatasetGroup::query()->max('sort')) + 1)
                ->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('sort')
                    ->label('№'),
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable()
                    ->limit(50)
                    ->url(fn (Dataset $record): string => static::getUrl('edit', ['record' => $record])),
                TextColumn::make('accrual_periodicity')
                    ->label('Частота оновлення')
                    ->formatStateUsing(fn (DatasetUpdateFrequency $state): string => $state->getLabel()),
                TextColumn::make('publisher.name')
                    ->label('Розпорядник'),
            ])
            ->defaultGroup(
                Group::make('group_id')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Dataset $record): string => $record->group->name ?? 'Інше')
                    ->collapsible(),
            )
            ->defaultSort('sort')
            ->reorderable('sort')
            ->searchable(true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'publisher',
                'group',
            ]))
            ->recordActions([
                Action::make('documentationLink')
                    ->label('Опис набору даних')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->iconButton()
                    ->url(fn (Dataset $record): ?string => $record->rec_code?->getExternalLink())
                    ->openUrlInNewTab()
                    ->visible(fn (Dataset $record): bool => $record->rec_code !== null),
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

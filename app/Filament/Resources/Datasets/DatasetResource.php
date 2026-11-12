<?php

namespace App\Filament\Resources\Datasets;

use App\Enums\ContactPointType;
use App\Enums\DatasetResourceFormat;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
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
                    ->maxLength(500),
                Textarea::make('description')
                    ->label('Опис')
                    ->required()
                    ->columnSpanFull(),
                Select::make('accrual_periodicity')
                    ->label('Частота оновлення')
                    ->options(DatasetUpdateFrequency::class)
                    ->required(),
                TagsInput::make('keywords')
                    ->label('Ключові слова')
                    ->placeholder('Введіть слово та натисніть Enter')
                    ->required(),
                Textarea::make('purpose')
                    ->label('Підстава та призначення збору інформації')
                    ->required()
                    ->columnSpanFull(),
                Select::make('distribution_formats')
                    ->label('Формати ресурсів')
                    ->options(DatasetResourceFormat::class)
                    ->multiple()
                    ->searchable()
                    ->required(),
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
                    ->getOptionLabelFromRecordUsing(fn (Official $record): string => self::contactOptionLabel($record))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->label('Ідентифікатор')
                    ->copyable(),
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
                    ->tooltip('Відкрити набір даних на порталі')
                    ->url(fn (Dataset $record): string => $record->url)
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->label('Редагувати'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDatasets::route('/'),
        ];
    }

    private static function contactOptionLabel(Official $record): string
    {
        $email = $record->contactPoints
            ->firstWhere('type', ContactPointType::Email)
            ?->value;

        return collect([$record->person->full_name, $record->position, $email])
            ->filter(filled(...))
            ->implode(' — ');
    }
}

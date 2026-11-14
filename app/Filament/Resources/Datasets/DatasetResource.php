<?php

namespace App\Filament\Resources\Datasets;

use App\Enums\DatasetRecommendation;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use App\Services\OpenDataPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

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
                Select::make('publisher_id')
                    ->label('Розпорядник')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('external_id')
                    ->label('Ідентифікаційний номер набору даних')
                    ->placeholder('Введіть ID або знайдіть за назвою')
                    ->suffixAction(
                        Action::make('searchPortalDataset')
                            ->label('Пошук набору на порталі за назвою')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->modalHeading('Пошук набору даних на порталі')
                            ->modalSubmitActionLabel('Вибрати')
                            ->schema([
                                Select::make('package_id')
                                    ->label('Набір даних')
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn (string $search, OpenDataPortal $portal): array => $portal->searchPackages($search),
                                    )
                                    ->getOptionLabelUsing(fn (?string $value): ?string => $value)
                                    ->required(),
                            ])
                            ->action(function (array $data, Set $schemaSet): void {
                                $packageId = $data['package_id'] ?? null;

                                if (! is_string($packageId) || blank($packageId)) {
                                    throw new UnexpectedValueException('Choose a dataset from the open data portal.');
                                }

                                $schemaSet('external_id', $packageId);
                            }),
                    )
                    ->live(onBlur: true)
                    ->rules(['nullable', 'uuid'])
                    ->unique(ignoreRecord: true),
                Section::make('Додаткова інформація з порталу')
                    ->key('portalMetadata')
                    ->schema([
                        TextEntry::make('meta.description')->label('Опис'),
                        TextEntry::make('meta.accrual_periodicity')->label('Частота оновлення'),
                        TextEntry::make('meta.keywords')->label('Ключові слова'),
                        TextEntry::make('meta.purpose')->label('Підстава та призначення'),
                        TextEntry::make('meta.contact_point_name')->label('Контактна особа'),
                        TextEntry::make('meta.contact_point_email')->label('Електронна пошта'),
                    ])
                    ->headerActions([
                        fn (): Action => Action::make('syncPortalMetadata')
                            ->label('Оновити')
                            ->icon(Heroicon::ArrowPath)
                            ->disabled(function (Get $schemaGet, mixed $record): bool {
                                return $schemaGet('external_id') !== $record->external_id;
                            })
                            ->action(function (
                                Get $schemaGet,
                                EditDataset $livewire,
                                OpenDataPortal $portal,
                            ): void {
                                $dataset = $livewire->getRecord();
                                $selectedPackageId = $schemaGet('external_id');

                                if (
                                    ! is_string($selectedPackageId)
                                    || blank($selectedPackageId)
                                    || $dataset->external_id !== $selectedPackageId
                                ) {
                                    Notification::make()
                                        ->title('Спочатку збережіть вибір набору на порталі.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                self::syncPortalMetadata($dataset, $portal);
                            }),
                    ])
                    ->visible(fn (mixed $record): bool => $record instanceof Dataset && filled($record->external_id))
                    ->columnSpanFull(),
            ]);
    }

    public static function syncPortalMetadata(Dataset $dataset, OpenDataPortal $portal): void
    {
        try {
            $portal->syncDatasetMetadata($dataset);
        } catch (UnexpectedValueException $exception) {
            Notification::make()
                ->title('Не вдалося завантажити додаткову інформацію')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Додаткову інформацію з порталу оновлено')
            ->success()
            ->send();
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
            ->paginated(false)
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
                    ->url(fn (Dataset $record): ?string => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (Dataset $record): bool => filled($record->external_id)),
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

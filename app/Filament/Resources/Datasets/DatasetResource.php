<?php

namespace App\Filament\Resources\Datasets;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use App\Enums\DatasetUpdateFrequency;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ManageDatasets;
use App\Filament\Resources\Datasets\RelationManagers\ResourcesRelationManager;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use App\Models\Organization;
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
use Filament\Tables\Filters\SelectFilter;
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
                    ->maxLength(500)
                    ->columnSpan(2),
                Select::make('group_id')
                    ->label('Група')
                    ->relationship('group', 'name')
                    ->preload()
                    ->columnSpan(2)
                    ->createOptionForm(static fn (): array => self::groupOptionForm())
                    ->editOptionForm(static fn (): array => self::groupOptionForm()),
                Select::make('publisher_id')
                    ->label('Розпорядник')
                    ->relationship('publisher', 'full_name')
                    ->searchable()
                    ->preload()
                    ->columnSpan(2)
                    ->default(fn (): ?int => Organization::query()->value('id'))
                    ->required(),
                TextInput::make('info_owner')
                    ->label('Власники інформації')
                    ->columnSpan(2)
                    ->maxLength(255),
                Select::make('status')
                    ->label('Статус')
                    ->options(DatasetStatus::class)
                    ->default(DatasetStatus::Collecting->value)
                    ->required()
                    ->live()
                    ->rule(Rule::enum(DatasetStatus::class)),
                TextInput::make('status_note')
                    ->label('Коментар до статусу')
                    ->required(fn (Get $get): bool => in_array($get('status'), [
                        DatasetStatus::NoInformation->value,
                        DatasetStatus::NotPublished->value,
                    ], true))
                    ->requiredIf('status', [
                        DatasetStatus::NoInformation->value,
                        DatasetStatus::NotPublished->value,
                    ])
                    ->maxLength(255),
                TextInput::make('external_id')
                    ->label('Ідентифікаційний номер набору даних')
                    ->columnSpan(2)
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
                        TextEntry::make('meta.accrual_periodicity')
                            ->label('Частота оновлення')
                            ->formatStateUsing(fn (string $state): string => DatasetUpdateFrequency::tryFrom($state)?->getLabel() ?? $state),
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
            ])->columns(4);
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
                    ->wrap()
                    ->limit(200)
                    ->url(fn (Dataset $record): string => static::getUrl('edit', ['record' => $record])),
                TextColumn::make('status')
                    ->label('Статус')
                    ->tooltip(fn (Dataset $record): ?string => $record->status_note),
                TextColumn::make('info_owner')
                    ->label('Розпорядники')
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(DatasetStatus::class),
            ])
            ->recordClasses(fn (Dataset $record): string => match ($record->status) {
                DatasetStatus::Unknown => 'dataset-status-unknown',
                DatasetStatus::NoInformation => 'dataset-status-no-information',
                DatasetStatus::NotPublished => 'dataset-status-not-published',
                DatasetStatus::Collecting => 'dataset-status-collecting',
                DatasetStatus::Published => 'dataset-status-published',
            })
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
                'group',
            ]))
            ->recordActions([
                self::documentationLinkAction()->iconButton(),
                self::portalLinkAction()->iconButton(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function documentationLinkAction(): Action
    {
        return Action::make('documentationLink')
            ->label('Про набір')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->url(fn (Dataset $record): ?string => $record->rec_code?->getExternalLink())
            ->openUrlInNewTab()
            ->visible(fn (Dataset $record): bool => $record->rec_code !== null);
    }

    public static function portalLinkAction(): Action
    {
        return Action::make('portalLink')
            ->label('На портал')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (Dataset $record): ?string => $record->url)
            ->openUrlInNewTab()
            ->visible(fn (Dataset $record): bool => filled($record->external_id));
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

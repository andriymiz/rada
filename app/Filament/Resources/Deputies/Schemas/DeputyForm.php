<?php

namespace App\Filament\Resources\Deputies\Schemas;

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Models\Convocation;
use App\Models\Deputy;
use App\Models\Faction;
use App\Models\PermanentCommission;
use App\Models\Person;
use App\Models\PoliticalParty;
use App\Rules\ContactPointValue;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use LogicException;

class DeputyForm
{
    const CVK_URL = 'https://www.cvk.gov.ua/pls/vm2020/pvm056pid102=10236pf7691=64048pt001f01=695rej=0pt00_t001f01=695.html';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::components());
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('person_id')
                        ->label('Особа')
                        ->relationship('person', 'last_name')
                        ->getOptionLabelFromRecordUsing(fn (Person $record): string => $record->full_name)
                        ->searchable(['last_name', 'first_name', 'middle_name'])
                        ->required()
                        ->disabledOn('edit')
                        ->rule(fn (RelationManager $livewire, ?Deputy $record) => Rule::unique('deputies', 'person_id')
                            ->where('convocation_id', self::getConvocationId($livewire))
                            ->ignore($record?->getKey()))
                        ->createOptionForm(self::personFields())
                        ->createOptionUsing(fn (array $data): int => (int) Person::query()
                            ->create($data)
                            ->getKey())
                        ->createOptionModalHeading('Нова особа'),
                    TextInput::make('voting_identifier')
                        ->label('Номер пульта')
                        ->default(fn (RelationManager $livewire): string => (string) self::nextVotingIdentifier($livewire))
                        ->maxLength(255),
                ])
                ->columnSpanFull(),
            Textarea::make('biography')
                ->label('Біографія')
                ->required()
                ->belowContent([
                    Flex::make([
                        Text::make('Інформацію про депутата можна переглянути на сторінці'),
                        Action::make('cvkLink')
                            ->label('ЦВК')
                            ->url(self::CVK_URL)
                            ->openUrlInNewTab()
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->iconPosition('after')
                            ->link(),
                    ])
                        ->extraAttributes(['class' => 'items-baseline']),
                ])
                ->columnSpanFull(),
            Grid::make(2)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('political_party_id')
                        ->label('Партія, що висувала')
                        ->relationship('politicalParty', 'name')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => PoliticalParty::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                        )
                        ->selectablePlaceholder(false)
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Назва партії')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('edrpou')
                                ->label('Код ЄДРПОУ')
                                ->length(8)
                                ->unique(table: 'political_parties', column: 'edrpou'),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) PoliticalParty::query()
                            ->create($data)
                            ->getKey()),
                    TextInput::make('area_id')
                        ->label('Номер виборчого округу')
                        ->numeric()
                        ->minValue(0),
                ])
                ->columnSpanFull(),
            Select::make('faction_id')
                ->label('Фракція')
                ->options(fn (RelationManager $livewire): array => Faction::query()
                    ->where('convocation_id', self::getConvocationId($livewire))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Назва фракції')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data, RelationManager $livewire): int {
                    return (int) Faction::query()->firstOrCreate([
                        'convocation_id' => self::getConvocationId($livewire),
                        'name' => $data['name'],
                    ])->getKey();
                })
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if (blank($state)) {
                        $set('faction_position', null);
                    }
                }),
            TextInput::make('faction_position')
                ->label('Посада у фракції')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => filled($get('faction_id'))),
            TextInput::make('role')
                ->label('Статус')
                ->default('Депутат місцевої ради')
                ->required()
                ->maxLength(255),
            FusedGroup::make([
                DatePicker::make('start_date')
                    ->label('Дата початку')
                    ->native(false)
                    ->placeholder('дд.мм.рррр')
                    ->default(fn (RelationManager $livewire, ?Deputy $record): ?string => $record === null
                        ? self::getConvocation($livewire)->date_from?->toDateString()
                        : null)
                    ->columnSpan(5),
                Text::make('—')
                    ->columnSpan(2),
                DatePicker::make('end_date')
                    ->label('Дата завершення')
                    ->native(false)
                    ->placeholder('дд.мм.рррр')
                    ->columnSpan(5),
            ])
                ->label('Термін повноважень')
                ->columns(12),
            Grid::make(2)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('homepage')
                        ->label('Сторінка на сайті ради')
                        ->url()
                        ->rule('url:http,https')
                        ->maxLength(2048),
                    TextInput::make('image')
                        ->label('Посилання на фото')
                        ->url()
                        ->rule('url:http,https')
                        ->maxLength(2048),
                ])
                ->columnSpanFull(),
            Grid::make(2)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('permanent_commission_id')
                        ->label('Комісія')
                        ->options(fn (RelationManager $livewire): array => PermanentCommission::query()
                            ->where('convocation_id', self::getConvocationId($livewire))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Назва комісії')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data, RelationManager $livewire): int {
                            return (int) PermanentCommission::query()->firstOrCreate([
                                'convocation_id' => self::getConvocationId($livewire),
                                'name' => $data['name'],
                            ])->getKey();
                        })
                        ->live(),
                    TextInput::make('commission_position')
                        ->label('Посада у комісії')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => filled($get('permanent_commission_id'))),
                ])
                ->columnSpanFull(),
            Section::make('Місце та графік прийому')
                ->relationship(
                    'reception',
                    fn (?Deputy $record): bool => $record?->reception()->exists() ?? false,
                )
                ->visible(fn (?Deputy $record): bool => $record?->reception()->exists() ?? false)
                ->columns(2)
                ->schema(self::receptionFields()),
            Section::make('Місце та графік прийому')
                ->key('reception-add-section')
                ->description('За потреби додайте адресу та графік прийому депутата.')
                ->footerActions([
                    Action::make('addReception')
                        ->label('Додати місце та графік прийому')
                        ->schema(self::receptionFields())
                        ->action(fn (array $data, Deputy $record) => $record->reception()->create($data)),
                ])
                ->visible(fn (?Deputy $record): bool => $record !== null && ! $record->reception()->exists()),
            Repeater::make('contactPoints')
                ->label('Контактні дані')
                ->relationship()
                ->defaultItems(0)
                ->table([
                    TableColumn::make('Тип контакту'),
                    TableColumn::make('Значення'),
                    TableColumn::make('Публічний'),
                    TableColumn::make('Примітка'),
                ])
                ->compact()
                ->schema([
                    Select::make('type')
                        ->label('Тип контакту')
                        ->options(ContactPointType::class)
                        ->required()
                        ->searchable()
                        ->native(false),
                    TextInput::make('value')
                        ->label('Значення')
                        ->required()
                        ->maxLength(2048)
                        ->rule(new ContactPointValue),
                    Toggle::make('is_public')
                        ->label('Публічний')
                        ->default(false),
                    TextInput::make('note')
                        ->label('Примітка')
                        ->maxLength(2048),
                ])
                ->addActionLabel('Додати контакт'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function personFields(): array
    {
        return [
            Grid::make(3)
                ->columns(['sm' => 2, 'lg' => 3])
                ->schema([
                    TextInput::make('last_name')
                        ->label('Прізвище')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('first_name')
                        ->label('Ім’я')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->label('По батькові')
                        ->required()
                        ->maxLength(255),
                ])
                ->columnSpanFull(),
            Grid::make(2)
                ->columns(['sm' => 2])
                ->schema([
                    Select::make('gender')
                        ->label('Стать')
                        ->options(Gender::class)
                        ->rule(Rule::in(array_map(
                            static fn (Gender $gender): int => $gender->value,
                            Gender::cases(),
                        )))
                        ->required()
                        ->native(false),
                    DatePicker::make('date_of_birth')
                        ->label('Дата народження')
                        ->required()
                        ->native(false)
                        ->maxDate(now()),
                ])
                ->columnSpanFull(),
        ];
    }

    private static function nextVotingIdentifier(RelationManager $livewire): int
    {
        $highestIdentifier = Deputy::query()
            ->where('convocation_id', self::getConvocationId($livewire))
            ->whereNotNull('voting_identifier')
            ->pluck('voting_identifier')
            ->max() ?? 0;

        return ((int) $highestIdentifier) + 1;
    }

    private static function getConvocationId(RelationManager $livewire): int
    {
        return (int) self::getConvocation($livewire)->getKey();
    }

    private static function getConvocation(RelationManager $livewire): Convocation
    {
        $parentRecord = $livewire->getOwnerRecord();

        if (! $parentRecord instanceof Convocation) {
            throw new LogicException('Deputy forms must be scoped to a convocation.');
        }

        return $parentRecord;
    }

    /**
     * @return array<int, TextInput>
     */
    private static function receptionFields(): array
    {
        return [
            TextInput::make('katoottg')
                ->label('Код КАТОТТГ')
                ->required()
                ->maxLength(19),
            TextInput::make('address_post_code')
                ->label('Поштовий індекс')
                ->required()
                ->maxLength(12),
            TextInput::make('address_admin_unit_l1')
                ->label('Країна')
                ->default('Україна')
                ->required()
                ->maxLength(255),
            TextInput::make('address_admin_unit_l2')
                ->label('Область')
                ->required()
                ->maxLength(255),
            TextInput::make('address_admin_unit_l3')
                ->label('Район')
                ->maxLength(255),
            TextInput::make('address_admin_unit_l4')
                ->label('Територіальна громада')
                ->maxLength(255),
            TextInput::make('address_post_name')
                ->label('Населений пункт')
                ->required()
                ->maxLength(255),
            TextInput::make('address_thoroughfare')
                ->label('Вулиця')
                ->required()
                ->maxLength(255),
            TextInput::make('address_locator_designator')
                ->label('Номер будинку')
                ->required()
                ->maxLength(255),
            TextInput::make('address_locator_building')
                ->label('Номер корпусу')
                ->maxLength(255),
            TextInput::make('address_locator_name')
                ->label('Назва будівлі чи приміщення')
                ->maxLength(255),
            TextInput::make('opening_hours')
                ->label('Графік прийому')
                ->required()
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('availability_restriction')
                ->label('Уточнення графіка')
                ->maxLength(2048)
                ->columnSpanFull(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Deputies\Schemas;

use App\Enums\Gender;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\CreateDeputy;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\EditDeputy;
use App\Models\Convocation;
use App\Models\Deputy;
use App\Models\Faction;
use App\Models\PermanentCommission;
use App\Models\Person;
use App\Models\PoliticalParty;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use LogicException;

class DeputyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->label('Особа')
                    ->relationship(
                        'person',
                        'last_name',
                        modifyQueryUsing: function (Builder $query, ?Deputy $record, CreateDeputy|EditDeputy $livewire): Builder {
                            $convocationId = self::getConvocationId($livewire);

                            return $query->where(fn (Builder $query): Builder => $query
                                ->whereDoesntHave(
                                    'deputies',
                                    fn (Builder $deputies): Builder => $deputies->where(
                                        'convocation_id',
                                        $convocationId,
                                    ),
                                )
                                ->when(
                                    $record?->person_id,
                                    fn (Builder $query, int $personId): Builder => $query->orWhereKey($personId),
                                ));
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (Person $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name', 'middle_name'])
                    ->required()
                    ->disabledOn('edit')
                    ->rule(fn (CreateDeputy|EditDeputy $livewire, ?Deputy $record) => Rule::unique('deputies', 'person_id')
                        ->where('convocation_id', self::getConvocationId($livewire))
                        ->ignore($record?->getKey()))
                    ->createOptionForm(self::personFields())
                    ->createOptionUsing(fn (array $data): int => (int) Person::query()
                        ->create($data)
                        ->getKey())
                    ->createOptionModalHeading('Нова особа')
                    ->createOptionAction(fn (Action $action): Action => $action->tooltip('Створити особу'))
                    ->native(false),
                Textarea::make('biography')
                    ->label('Біографія')
                    ->required()
                    ->columnSpanFull(),
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
                TextInput::make('voting_identifier')
                    ->label('Номер у системі голосування')
                    ->maxLength(255)
                    ->unique(
                        table: 'deputies',
                        column: 'voting_identifier',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where(
                            'convocation_id',
                            $get('convocation_id'),
                        ),
                    ),
                Select::make('faction_id')
                    ->label('Фракція')
                    ->options(fn (Get $get): array => Faction::query()
                        ->where('convocation_id', $get('convocation_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->disabled(fn (Get $get): bool => blank($get('convocation_id')))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Назва фракції')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data, Get $get): int {
                        $convocationId = $get('convocation_id');

                        if (! filled($convocationId)) {
                            throw ValidationException::withMessages([
                                'convocation_id' => 'Спочатку виберіть скликання.',
                            ]);
                        }

                        return (int) Faction::query()->firstOrCreate([
                            'convocation_id' => $convocationId,
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
                TextInput::make('area_id')
                    ->label('Номер виборчого округу')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('role')
                    ->label('Статус')
                    ->default('Депутат місцевої ради')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('start_date')
                    ->label('Дата початку повноважень')
                    ->native(false),
                DatePicker::make('end_date')
                    ->label('Дата завершення повноважень')
                    ->native(false),
                TextInput::make('homepage')
                    ->label('Сторінка на сайті ради')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048)
                    ->columnSpanFull(),
                Section::make('Постійна комісія')
                    ->columns(2)
                    ->schema([
                        Select::make('permanent_commission_id')
                            ->label('Комісія')
                            ->options(fn (Get $get): array => PermanentCommission::query()
                                ->where('convocation_id', $get('convocation_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->disabled(fn (Get $get): bool => blank($get('convocation_id')))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Назва комісії')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, Get $get): int {
                                $convocationId = $get('convocation_id');

                                if (! filled($convocationId)) {
                                    throw ValidationException::withMessages([
                                        'convocation_id' => 'Спочатку виберіть скликання.',
                                    ]);
                                }

                                return (int) PermanentCommission::query()->firstOrCreate([
                                    'convocation_id' => $convocationId,
                                    'name' => $data['name'],
                                ])->getKey();
                            })
                            ->live(),
                        TextInput::make('commission_position')
                            ->label('Посада у комісії')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => filled($get('permanent_commission_id'))),
                    ]),
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
            ]);
    }

    /**
     * @return array<int, TextInput|Select|DatePicker>
     */
    private static function personFields(): array
    {
        return [
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
            TextInput::make('image')
                ->label('Посилання на фото')
                ->url()
                ->rule('url:http,https')
                ->maxLength(2048)
                ->columnSpanFull(),
        ];
    }

    private static function getConvocationId(CreateDeputy|EditDeputy $livewire): int
    {
        $parentRecord = $livewire->getParentRecord();

        if (! $parentRecord instanceof Convocation) {
            throw new LogicException('Deputy forms must be scoped to a convocation.');
        }

        return (int) $parentRecord->getKey();
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

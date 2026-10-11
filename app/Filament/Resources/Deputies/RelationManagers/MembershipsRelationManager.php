<?php

namespace App\Filament\Resources\Deputies\RelationManagers;

use App\Models\Convocation;
use App\Models\Faction;
use App\Models\PermanentCommission;
use App\Models\PoliticalParty;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Скликання та політична належність')
                    ->columns(2)
                    ->schema([
                        Select::make('convocation_id')
                            ->label('Скликання')
                            ->options(fn (): array => Convocation::query()
                                ->whereNotNull('organization_id')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('permanent_commission_id', null);
                                $set('faction_id', null);
                                $set('faction_position', null);
                            }),
                        TextInput::make('voting_identifier')
                            ->label('Номер у системі голосування')
                            ->maxLength(255)
                            ->unique(
                                table: 'memberships',
                                column: 'voting_identifier',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where(
                                    'convocation_id',
                                    $get('convocation_id'),
                                ),
                            ),
                        Select::make('political_party_id')
                            ->label('Партія, що висувала')
                            ->relationship('politicalParty', 'name')
                            ->required()
                            ->searchable()
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
                    ]),
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'convocation',
                'faction',
                'politicalParty',
                'permanentCommission',
            ]))
            ->recordTitleAttribute('role')
            ->columns([
                TextColumn::make('role')
                    ->label('Статус'),
                TextColumn::make('convocation.name')
                    ->label('Скликання'),
                TextColumn::make('politicalParty.name')
                    ->label('Партія'),
                TextColumn::make('faction.name')
                    ->label('Фракція')
                    ->placeholder('—'),
                TextColumn::make('voting_identifier')
                    ->label('Номер у системі голосування')
                    ->placeholder('—'),
                TextColumn::make('permanentCommission.name')
                    ->label('Комісія')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Додати скликання')
                    ->mutateDataUsing(fn (array $data): array => $this->withConvocationOrganization($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->withConvocationOrganization($data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withConvocationOrganization(array $data): array
    {
        $convocation = Convocation::query()
            ->whereKey($data['convocation_id'] ?? null)
            ->first();

        if ($convocation === null || $convocation->organization_id === null) {
            throw ValidationException::withMessages([
                'convocation_id' => 'Виберіть скликання, прив’язане до організації ради.',
            ]);
        }

        return [
            ...$data,
            'organization_id' => $convocation->organization_id,
        ];
    }
}

<?php

namespace App\Filament\Resources\PlenaryMeetings\RelationManagers;

use App\Enums\MotionResult;
use App\Enums\VoteOption;
use App\Models\Motion;
use App\Models\Person;
use App\Models\PlenaryMeeting;
use App\Models\Vote;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class MotionsRelationManager extends RelationManager
{
    protected static string $relationship = 'motions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('number')
                    ->label('Номер питання')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->unique(
                        table: 'motions',
                        column: 'number',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                            'plenary_meeting_id',
                            $this->getOwnerRecord()->getKey(),
                        ),
                    ),
                TextInput::make('project_number')
                    ->label('Номер проєкту рішення')
                    ->maxLength(255),
                Textarea::make('title')
                    ->label('Назва питання')
                    ->required()
                    ->columnSpanFull(),
                Select::make('result')
                    ->label('Результат розгляду')
                    ->options(MotionResult::class)
                    ->required(),
                TextInput::make('text')
                    ->label('Назва документа')
                    ->required()
                    ->maxLength(255),
                TextInput::make('text_url')
                    ->label('Посилання на документ')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                Repeater::make('voteEvents')
                    ->label('Поіменні голосування')
                    ->relationship()
                    ->schema([
                        Repeater::make('votes')
                            ->label('Голоси депутатів')
                            ->relationship()
                            ->schema([
                                Select::make('person_id')
                                    ->label('Депутат')
                                    ->options(fn (): array => Person::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->distinct(),
                                Select::make('option')
                                    ->label('Голос')
                                    ->options(VoteOption::class)
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(2)
                            ->addActionLabel('Додати голос депутата')
                            ->defaultItems(0),
                    ])
                    ->addActionLabel('Додати поіменне голосування')
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('voteEvents.votes'))
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('number')
                    ->label('№')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Питання')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('result')
                    ->label('Результат')
                    ->badge(),
                ...collect([
                    VoteOption::Yes,
                    VoteOption::No,
                    VoteOption::Abstain,
                    VoteOption::NotVoting,
                    VoteOption::Absent,
                    VoteOption::Paired,
                ])->map(fn (VoteOption $option): TextColumn => TextColumn::make('votes_'.$option->value)
                    ->label($option->getLabel())
                    ->state(fn (Motion $record): int => $record->voteEvents
                        ->flatMap(fn ($event) => $event->votes)
                        ->filter(fn (Vote $vote): bool => $vote->option === $option)
                        ->count()))
                    ->all(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Додати питання')
                    ->mutateDataUsing(function (array $data): array {
                        /** @var PlenaryMeeting $meeting */
                        $meeting = $this->getOwnerRecord();

                        return [
                            ...$data,
                            'uid' => "{$meeting->date->toDateString()}-{$meeting->getKey()}-{$data['number']}",
                        ];
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Редагувати'),
            ])
            ->defaultSort('number');
    }
}

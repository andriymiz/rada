<?php

namespace App\Filament\Resources\RollCallImports\Schemas;

use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use LogicException;

class RollCallImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('PDF-файл із поіменними голосуваннями')
                    ->disk('local')
                    ->directory('roll-call-imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('original_filename')
                    ->required(),
                Select::make('convocation_id')
                    ->label('Скликання')
                    ->options(fn (): array => ParliamentaryConvocation::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $state): ?string => $state
                        ? ParliamentaryConvocation::query()->whereKey($state)->first()?->name
                        : null)
                    ->getSelectedRecordUsing(fn (mixed $state): ?ParliamentaryConvocation => $state
                        ? ParliamentaryConvocation::query()->whereKey($state)->first()
                        : null)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Назва скликання')
                            ->required()
                            ->maxLength(100)
                            ->unique(table: ParliamentaryConvocation::class, column: 'name'),
                    ])
                    ->createOptionUsing(fn (array $data): int => (int) ParliamentaryConvocation::query()
                        ->create($data)
                        ->getKey())
                    ->editOptionForm([
                        TextInput::make('name')
                            ->label('Назва скликання')
                            ->required()
                            ->maxLength(100)
                            ->unique(table: ParliamentaryConvocation::class, column: 'name', ignoreRecord: true),
                    ])
                    ->updateOptionUsing(function (array $data, Schema $schema): void {
                        $record = $schema->getRecord();

                        if (! $record instanceof ParliamentaryConvocation) {
                            throw new LogicException('The selected convocation could not be loaded.');
                        }

                        $record->update($data);
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('session_id', null))
                    ->rules(['required', 'exists:parliamentary_convocations,id'])
                    ->dehydrated(false)
                    ->required(),
                Select::make('session_id')
                    ->label('Сесія')
                    ->relationship(
                        name: 'session',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('convocation_id', $get('convocation_id')),
                    )
                    ->createOptionForm(fn (Get $get): array => [
                        TextInput::make('name')
                            ->label('Назва сесії')
                            ->required()
                            ->maxLength(100)
                            ->unique(
                                table: ParliamentarySession::class,
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('convocation_id', $get('convocation_id')),
                            ),
                    ])
                    ->createOptionUsing(function (array $data, Get $get): int {
                        return (int) ParliamentarySession::query()
                            ->create([
                                ...$data,
                                'convocation_id' => $get('convocation_id'),
                            ])
                            ->getKey();
                    })
                    ->editOptionForm(fn (Get $get): array => [
                        TextInput::make('name')
                            ->label('Назва сесії')
                            ->required()
                            ->maxLength(100)
                            ->unique(
                                table: ParliamentarySession::class,
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('convocation_id', $get('convocation_id')),
                            ),
                    ])
                    ->searchable()
                    ->disabled(fn (Get $get): bool => blank($get('convocation_id')))
                    ->required(),
            ]);
    }
}

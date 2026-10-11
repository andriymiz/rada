<?php

namespace App\Filament\Resources\PlenaryMeetings\Schemas;

use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class PlenaryMeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::components());
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Select::make('parliamentary_session_id')
                ->label('Сесія')
                ->options(fn (): array => ParliamentarySession::query()
                    ->with('convocation')
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(fn (ParliamentarySession $session): array => [
                        $session->id => "{$session->name} ({$session->convocation->name} скликання)",
                    ])
                    ->all())
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Назва сесії')
                        ->required()
                        ->maxLength(255),
                    Select::make('convocation_id')
                        ->label('Скликання')
                        ->options(fn (): array => ParliamentaryConvocation::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Назва скликання')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) ParliamentaryConvocation::query()
                            ->create($data)
                            ->getKey()),
                ])
                ->createOptionUsing(fn (array $data): int => (int) ParliamentarySession::query()
                    ->create($data)
                    ->getKey())
                ->createOptionModalHeading('Нова сесія')
                ->required()
                ->searchable()
                ->selectablePlaceholder(false)
                ->native(false),
            DatePicker::make('date')
                ->label('Дата засідання')
                ->required()
                ->native(false),
        ];
    }
}

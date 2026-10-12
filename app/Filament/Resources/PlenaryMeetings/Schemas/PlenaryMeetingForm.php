<?php

namespace App\Filament\Resources\PlenaryMeetings\Schemas;

use App\Models\Convocation;
use App\Models\CouncilSession;
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
            Select::make('council_session_id')
                ->label('Сесія')
                ->options(fn (): array => CouncilSession::query()
                    ->whereHas('convocation', fn ($query) => $query->whereNotNull('organization_id'))
                    ->with('convocation')
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(fn (CouncilSession $session): array => [
                        $session->id => "{$session->name} ({$session->convocation->label})",
                    ])
                    ->all())
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Назва сесії')
                        ->required()
                        ->maxLength(255),
                    Select::make('convocation_id')
                        ->label('Скликання')
                        ->options(fn (): array => Convocation::query()
                            ->whereNotNull('organization_id')
                            ->orderBy('label')
                            ->pluck('label', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->createOptionForm([
                            TextInput::make('label')
                                ->label('Назва скликання')
                                ->required()
                                ->maxLength(255),
                            DatePicker::make('date_from')
                                ->label('Дата початку')
                                ->native(false),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) Convocation::query()
                            ->create($data)
                            ->getKey()),
                ])
                ->createOptionUsing(fn (array $data): int => (int) CouncilSession::query()
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

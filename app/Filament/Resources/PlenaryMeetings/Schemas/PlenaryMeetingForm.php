<?php

namespace App\Filament\Resources\PlenaryMeetings\Schemas;

use App\Models\Convocation;
use App\Models\CouncilOrganization;
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
                        ->options(fn (): array => Convocation::query()
                            ->whereNotNull('organization_id')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->createOptionForm([
                            Select::make('organization_id')
                                ->label('Організація ради')
                                ->options(fn (): array => CouncilOrganization::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->required()
                                ->searchable()
                                ->native(false),
                            TextInput::make('name')
                                ->label('Назва скликання')
                                ->required()
                                ->maxLength(255),
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

<?php

namespace App\Filament\Resources\PlenaryMeetings\Schemas;

use App\Models\CouncilOrganization;
use App\Models\ParliamentarySession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
            Select::make('organization_id')
                ->label('Орган місцевого самоврядування')
                ->options(fn (): array => CouncilOrganization::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->native(false),
            Select::make('parliamentary_session_id')
                ->label('Сесія')
                ->options(fn (): array => ParliamentarySession::query()
                    ->with('convocation')
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(fn (ParliamentarySession $session): array => [
                        $session->id => "{$session->name} ({$session->convocation->name})",
                    ])
                    ->all())
                ->required()
                ->searchable()
                ->native(false),
            DatePicker::make('date')
                ->label('Дата засідання')
                ->required()
                ->native(false),
        ];
    }
}

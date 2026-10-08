<?php

namespace App\Filament\Resources\PlenaryMeetings;

use App\Filament\Resources\PlenaryMeetings\Pages\EditPlenaryMeeting;
use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Filament\Resources\PlenaryMeetings\Schemas\PlenaryMeetingForm;
use App\Filament\Resources\PlenaryMeetings\Tables\PlenaryMeetingsTable;
use App\Models\PlenaryMeeting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlenaryMeetingResource extends Resource
{
    protected static ?string $model = PlenaryMeeting::class;

    protected static ?string $slug = 'meetings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'засідання';

    protected static ?string $pluralModelLabel = 'Засідання';

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return PlenaryMeetingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlenaryMeetingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlenaryMeetings::route('/'),
            'edit' => EditPlenaryMeeting::route('/{record}/edit'),
        ];
    }
}

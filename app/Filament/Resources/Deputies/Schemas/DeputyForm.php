<?php

namespace App\Filament\Resources\Deputies\Schemas;

use App\Enums\Gender;
use App\Models\Deputy;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class DeputyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->required(),
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
                TextInput::make('homepage')
                    ->label('Сторінка на сайті ради')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048)
                    ->columnSpanFull(),
                Textarea::make('biography')
                    ->label('Біографія')
                    ->required()
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
            ]);
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

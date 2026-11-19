<?php

namespace App\Filament\Resources\Officials\Schemas;

use App\Filament\Resources\People\Schemas\PersonForm;
use App\Models\Employee;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OfficialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->label('Особа')
                    ->relationship('person', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Person $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name', 'middle_name'])
                    ->required()
                    ->disabledOn('edit')
                    ->createOptionForm(PersonForm::components())
                    ->createOptionUsing(fn (array $data): int => (int) Person::query()
                        ->create($data)
                        ->getKey())
                    ->createOptionModalHeading('Нова особа'),
                TextInput::make('position')
                    ->label('Посада')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_post')
                    ->label('Працівник'),
                TextInput::make('image')
                    ->label('Посилання на фото')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                TextInput::make('homepage')
                    ->label('Сторінка на сайті')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                Repeater::make('social_accounts')
                    ->label('Соціальні мережі')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->simple(TextInput::make('url')->url()->rule('url:http,https')->maxLength(2048)->required()),
                TextInput::make('email')
                    ->label('Електронна пошта')
                    ->email()
                    ->maxLength(255)
                    ->required(),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->maxLength(255)
                    ->rule('not_regex:/,/')
                    ->required(),
                Repeater::make('additional_contacts')
                    ->label('Додаткові контакти')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->simple(TextInput::make('value')->maxLength(255)->required()),
                Textarea::make('opening_hours')->label('Графік прийому')->maxLength(2048),
                Textarea::make('availability_restriction')->label('Обмеження доступності')->maxLength(2048),
                Section::make('Місце прийому')
                    ->columnSpanFull()
                    ->relationship(
                        'reception',
                        fn (?Employee $record): bool => $record?->reception()->exists() ?? false,
                    )
                    ->visible(fn (?Employee $record): bool => $record?->reception()->exists() ?? false)
                    ->schema(self::receptionFields()),
                Section::make('Місце прийому')
                    ->columnSpanFull()
                    ->key('reception-add-section')
                    ->description('За потреби додайте адресу прийому працівника.')
                    ->footerActions([
                        Action::make('addReception')
                            ->label('Додати місце прийому')
                            ->schema(self::receptionFields())
                            ->action(fn (array $data, Employee $record) => $record->reception()->create($data)),
                    ])
                    ->visible(fn (?Employee $record): bool => $record !== null && ! $record->reception()->exists()),
            ]);
    }

    /**
     * @return array<int, Component>
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
        ];
    }
}

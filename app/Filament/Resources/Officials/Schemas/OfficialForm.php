<?php

namespace App\Filament\Resources\Officials\Schemas;

use App\Enums\ContactPointType;
use App\Filament\Resources\People\Schemas\PersonForm;
use App\Models\Official;
use App\Models\Person;
use App\Rules\ContactPointValue;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
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
                Select::make('organization_id')
                    ->label('Організація')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('position')
                    ->label('Посада')
                    ->required()
                    ->maxLength(255),
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
                Repeater::make('contactPoints')
                    ->label('Контактні дані')
                    ->columnSpanFull()
                    ->relationship()
                    ->defaultItems(0)
                    ->table([
                        TableColumn::make('Тип контакту'),
                        TableColumn::make('Значення'),
                        TableColumn::make('Публічний'),
                        TableColumn::make('Примітка'),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('type')
                            ->label('Тип контакту')
                            ->options(ContactPointType::class)
                            ->required()
                            ->searchable()
                            ->native(false),
                        TextInput::make('value')
                            ->label('Значення')
                            ->required()
                            ->maxLength(2048)
                            ->rule(new ContactPointValue),
                        Toggle::make('is_public')
                            ->label('Публічний')
                            ->default(false),
                        TextInput::make('note')
                            ->label('Примітка')
                            ->maxLength(2048),
                    ])
                    ->addActionLabel('Додати контакт'),
                Section::make('Місце та графік прийому')
                    ->columnSpanFull()
                    ->relationship(
                        'reception',
                        fn (?Official $record): bool => $record?->reception()->exists() ?? false,
                    )
                    ->visible(fn (?Official $record): bool => $record?->reception()->exists() ?? false)
                    ->schema(self::receptionFields()),
                Section::make('Місце та графік прийому')
                    ->columnSpanFull()
                    ->key('reception-add-section')
                    ->description('За потреби додайте адресу та графік прийому посадовця.')
                    ->footerActions([
                        Action::make('addReception')
                            ->label('Додати місце та графік прийому')
                            ->schema(self::receptionFields())
                            ->action(fn (array $data, Official $record) => $record->reception()->create($data)),
                    ])
                    ->visible(fn (?Official $record): bool => $record !== null && ! $record->reception()->exists()),
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

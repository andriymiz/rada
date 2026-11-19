<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Models\Official;
use App\Models\Organization;
use App\Models\OrganizationGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->length(8)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('full_name')
                    ->label('Повна назва за ЄДР')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_name')
                    ->label('Коротка назва')
                    ->required()
                    ->maxLength(255),
                Select::make('group_id')
                    ->label('Група')
                    ->relationship('group', 'name')
                    ->preload()
                    ->createOptionForm(self::groupOptionForm())
                    ->editOptionForm(self::groupOptionForm()),
                Select::make('head_id')
                    ->label('Керівник')
                    ->relationship(
                        'head',
                        'position',
                        modifyQueryUsing: fn (Builder $query, ?Organization $record): Builder => $query
                            ->where('organization_id', $record?->getKey() ?? 0)
                            ->with('person'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Official $record): string => $record->person->full_name.' — '.$record->position)
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search, ?Organization $record): array {
                        if ($record === null) {
                            return [];
                        }

                        $parts = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                        return Official::query()
                            ->whereBelongsTo($record, 'organization')
                            ->where(fn (Builder $query): Builder => $query
                                ->where('position', 'like', "%{$search}%")
                                ->orWhereHas('person', function (Builder $person) use ($parts): void {
                                    foreach ($parts as $part) {
                                        $person->where(fn (Builder $names): Builder => $names
                                            ->where('last_name', 'like', "%{$part}%")
                                            ->orWhere('first_name', 'like', "%{$part}%")
                                            ->orWhere('middle_name', 'like', "%{$part}%"));
                                    }
                                }))
                            ->with('person')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Official $official): array => [
                                $official->getKey() => $official->person->full_name.' — '.$official->position,
                            ])
                            ->all();
                    })
                    ->nullable()
                    ->rule(fn (?Organization $record) => Rule::exists('officials', 'id')
                        ->where('organization_id', $record?->getKey() ?? 0)),
                TextInput::make('logo')
                    ->label('Логотип')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                Textarea::make('description')
                    ->label('Опис')
                    ->maxLength(300)
                    ->required()
                    ->columnSpanFull(),
                Select::make('parent_org_id')
                    ->label('Належність до сфери управління')
                    ->relationship(
                        'parentOrganization',
                        'full_name',
                        modifyQueryUsing: fn (Builder $query, ?Organization $record): Builder => $query
                            ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey())),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('constituent_doc')
                    ->label('Установчий документ')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                TextInput::make('homepage')
                    ->label('Офіційний веб-сайт')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(2048),
                TextInput::make('contact_point')
                    ->label('Контактна особа')
                    ->maxLength(255),
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
                Repeater::make('social_accounts')
                    ->label('Соціальні мережі')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->simple(TextInput::make('url')->url()->rule('url:http,https')->maxLength(2048)->required()),
                Textarea::make('opening_hours')
                    ->label('Графік роботи')
                    ->maxLength(2048),
                Textarea::make('availability_restriction')
                    ->label('Обмеження доступності')
                    ->maxLength(2048),
                TextInput::make('katoottg')
                    ->label('КАТОТТГ')
                    ->length(19),
            ]);
    }

    /**
     * @return array<int, TextInput>
     */
    private static function groupOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label('Назва групи')
                ->required()
                ->maxLength(255)
                ->unique(OrganizationGroup::class, 'name', ignoreRecord: true),
        ];
    }
}

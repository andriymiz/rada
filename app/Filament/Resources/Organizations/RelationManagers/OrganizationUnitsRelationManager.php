<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Models\Official;
use App\Models\OrganizationUnit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class OrganizationUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizationUnits';

    protected static ?string $title = 'Підрозділи';

    protected static ?string $modelLabel = 'підрозділ';

    protected static ?string $pluralModelLabel = 'підрозділи';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uid')->label('Ідентифікатор')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('full_name')->label('Повна назва')->required()->maxLength(255),
                TextInput::make('short_name')->label('Коротка назва')->maxLength(255),
                Textarea::make('description')->label('Опис')->required()->maxLength(300)->columnSpanFull(),
                TextInput::make('constituent_doc')->label('Установчий документ')->url()->rule('url:http,https')->maxLength(2048),
                TextInput::make('homepage')->label('Веб-сторінка')->url()->rule('url:http,https')->maxLength(2048),
                Select::make('head_id')
                    ->label('Керівник')
                    ->relationship('head', 'position', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->where('organization_id', $this->getOwnerRecord()->getKey())
                        ->with('person'))
                    ->getOptionLabelFromRecordUsing(fn (Official $record): string => $record->person->full_name.' — '.$record->position)
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        $parts = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                        return Official::query()
                            ->where('organization_id', $this->getOwnerRecord()->getKey())
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
                            ->with('person')->limit(50)->get()
                            ->mapWithKeys(fn (Official $official): array => [$official->getKey() => $official->person->full_name.' — '.$official->position])
                            ->all();
                    })
                    ->required()
                    ->rule(Rule::exists('officials', 'id')->where('organization_id', $this->getOwnerRecord()->getKey())),
                Select::make('parent_unit_id')
                    ->label('Підпорядкування')
                    ->relationship('parentUnit', 'full_name', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->where('organization_id', $this->getOwnerRecord()->getKey()))
                    ->searchable()
                    ->rule(Rule::exists('organization_units', 'id')->where('organization_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('contact_point')->label('Контактна особа або підрозділ')->required()->maxLength(255),
                Repeater::make('emails')
                    ->label('Електронні адреси')->required()->minItems(1)->defaultItems(1)
                    ->simple(TextInput::make('email')->email()->maxLength(255)->required()),
                Repeater::make('phones')
                    ->label('Телефони')->required()->minItems(1)->defaultItems(1)
                    ->simple(TextInput::make('phone')->maxLength(255)->rule('not_regex:/,/')->required()),
                Textarea::make('opening_hours')->label('Графік прийому')->maxLength(2048),
                Textarea::make('availability_restriction')->label('Обмеження прийому')->maxLength(2048),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Назва')->searchable()->sortable(),
                TextColumn::make('uid')->label('Ідентифікатор')->searchable()->sortable(),
                TextColumn::make('head.person.full_name')->label('Керівник'),
                TextColumn::make('parentUnit.full_name')->label('Підпорядкування'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['head.person', 'parentUnit'])->withCount('childUnits'))
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (OrganizationUnit $record): bool => $record->child_units_count > 0)
                    ->tooltip(fn (OrganizationUnit $record): ?string => $record->child_units_count > 0 ? 'Спершу змініть підпорядкування дочірніх підрозділів.' : null),
            ]);
    }
}

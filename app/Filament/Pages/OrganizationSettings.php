<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Models\CouncilOrganization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class OrganizationSettings extends Page
{
    use HasRadaBreadcrumbs;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    protected static ?string $slug = 'organization';

    protected static ?string $title = 'Організація';

    protected static ?string $navigationLabel = 'Організація';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $organization = CouncilOrganization::query()->first();

        $this->form
            ->model($organization ?? CouncilOrganization::class)
            ->fill($organization?->attributesToArray() ?? []);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(CouncilOrganization::query()->first() ?? CouncilOrganization::class)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Назва організації')
                    ->required()
                    ->maxLength(255),
                TextInput::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->length(8)
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('katoottg')
                    ->label('КАТОТТГ')
                    ->length(19)
                    ->unique(ignoreRecord: true)
                    ->required(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $organization = CouncilOrganization::query()->first() ?? new CouncilOrganization;

        $organization->fill($data);
        $organization->save();

        $this->form->model($organization);

        Notification::make()
            ->title('Дані організації збережено')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return FormComponent::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                SchemaActions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth(false)
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Зберегти')
                ->submit('save'),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Організація';
    }
}

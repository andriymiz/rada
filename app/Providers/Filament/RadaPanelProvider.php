<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\RadaLogin;
use App\Filament\RadaPanelTheme;
use Filament\Actions\DeleteAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class RadaPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->paginationMode(PaginationMode::Default);
        });
        Select::configureUsing(function (Select $select): void {
            $select->native(false);
        });
        EditAction::configureUsing(function (EditAction $action): void {
            $action->iconButton();
        });
        ViewAction::configureUsing(function (ViewAction $action): void {
            $action->iconButton();
        });
        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $action->iconButton()->color('primary');
        });
        DetachAction::configureUsing(function (DetachAction $action): void {
            $action->iconButton();
        });
    }

    public function panel(Panel $panel): Panel
    {
        return RadaPanelTheme::apply($panel)
            ->default()
            ->id('rada')
            ->path('panel')
            ->login(RadaLogin::class)
            ->breadcrumbs(hasNavigationHierarchy: true)
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

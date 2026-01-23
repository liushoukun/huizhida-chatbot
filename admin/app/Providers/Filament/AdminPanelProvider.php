<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use HuiZhiDa\Filament\FilamentPlugin;
use RedJasmine\FilamentAdmin\FilamentAdminPlugin;
use RedJasmine\FilamentProject\FilamentProjectPlugin;
use RedJasmine\FilamentSupport\FilamentSupportPlugin;

class AdminPanelProvider extends \RedJasmine\FilamentSupport\Panel\PanelProvider
{
    public function panel(Panel $panel) : Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path('admin');

        $panel = parent::configure($panel);
        $panel->authGuard('admin-panel');
        $panel->colors([
                  'primary' => Color::Amber,
              ])
              ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
              ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
              ->pages([
                  Dashboard::class,
              ])->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')

            ->plugins([
                FilamentSupportPlugin::make(),
                FilamentAdminPlugin::make(),
                FilamentProjectPlugin::make(),
                FilamentPlugin::make(),
            ])
             ;

        return $panel;
    }
}

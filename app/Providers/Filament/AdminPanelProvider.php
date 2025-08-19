<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Nwidart\Modules\Facades\Module;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('admin-css', asset('css/admin.css')),
        ]);
    }

    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn() => config('app.name') . ' ' . __('Admin'))
            // ->brandLogo('https://raw.githubusercontent.com/swolley/images/refs/heads/master/swolley-1.jpg')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Green,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AdminMiddleware::class,
            ]);

        $this->discoverModulesFilamentComponents($panel);

        return $panel;
    }

    /**
     * Discover resources, pages and widgets from modules.
     */
    private function discoverModulesFilamentComponents(Panel $panel): void
    {
        // Add resources, pages and widgets from modules
        foreach (Module::all() as $module) {
            $moduleName = $module->getName();
            $modulePath = module_path($moduleName) . '/app';

            // Add resources from module
            $this->discoverModuleFilamentResources($panel, $moduleName, $modulePath);

            // Add pages from module
            $this->discoverModuleFilamentPages($panel, $moduleName, $modulePath);

            // Add widgets from module
            $this->discoverModuleFilamentWidgets($panel, $moduleName, $modulePath);
        }
    }

    /**
     * Discover resources from module.
     */
    private function discoverModuleFilamentResources(Panel $panel, string $moduleName, string $modulePath): void
    {
        if (is_dir($modulePath . '/Filament/Resources')) {
            $panel->discoverResources(
                in: $modulePath . '/Filament/Resources',
                for: "Modules\\{$moduleName}\\Filament\\Resources",
            );
        }
    }

    /**
     * Discover pages from module.
     */
    private function discoverModuleFilamentPages(Panel $panel, string $moduleName, string $modulePath): void
    {
        if (is_dir($modulePath . '/Filament/Pages')) {
            $panel->discoverPages(
                in: $modulePath . '/Filament/Pages',
                for: "Modules\\{$moduleName}\\Filament\\Pages",
            );
        }
    }

    /**
     * Discover widgets from module.
     */
    private function discoverModuleFilamentWidgets(Panel $panel, string $moduleName, string $modulePath): void
    {
        if (is_dir($modulePath . '/Filament/Widgets')) {
            $panel->discoverWidgets(
                in: $modulePath . '/Filament/Widgets',
                for: "Modules\\{$moduleName}\\Filament\\Widgets",
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Coolsam\Modules\ModulesPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use Stephenjude\FilamentDebugger\DebuggerPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function __construct() {}

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('admin-css', asset('css/admin.css')),
        ]);
    }

    public function panel(Panel $panel): Panel
    {
        $panel
            // GENERAL
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn() => config('app.name') . ' ' . __('Admin'))
            // ->brandLogo('https://raw.githubusercontent.com/swolley/images/refs/heads/master/swolley-1.jpg')
            ->spa(hasPrefetching: true)
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Green,
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->plugins([
                ModulesPlugin::make(),
                EnvironmentIndicatorPlugin::make()
                    ->visible(true)
                    ->showBorder(false)
                    ->showGitBranch()
                    ->showDebugModeWarningInProduction(),
                DebuggerPlugin::make()
                    ->navigationGroup(label: 'Health')
                    ->horizonNavigation(
                        condition: class_exists('Laravel\Horizon\HorizonServiceProvider'),
                        label: 'Queues',
                        icon: 'heroicon-' . Heroicon::OutlinedQueueList->value,
                        openInNewTab: false,
                        url: url(config('horizon.path'))
                    )
                    ->telescopeNavigation(condition: class_exists('Laravel\Telescope\TelescopeServiceProvider'), openInNewTab: false, url: url('telescope'))
                    ->pulseNavigation(condition: class_exists('Laravel\Pulse\PulseServiceProvider'), openInNewTab: false, url: url('pulse'))
            ])
            // AUTHENTICATION
            ->login()
            ->profile()
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            // ->requiresMultiFactorAuthentication()
            ->revealablePasswords(false)
            ->authGuard('admin')
            // MIDDLEWARES
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
            ])
        ;

        return $panel;
    }
}

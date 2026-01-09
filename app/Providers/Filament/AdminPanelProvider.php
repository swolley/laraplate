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
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;

final class AdminPanelProvider extends PanelProvider
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
            ->brandName(static fn (): string => sprintf('%s %s', (string) (config('app.name')), (string) (__('Admin'))))
            ->brandLogo(static fn (): ?string => config('app.logo') ?: 'https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true')
            ->spa(hasPrefetching: true)
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Green,
            ])
            ->pages([
                Pages\Dashboard::class,
                \Modules\Core\Filament\Pages\Documentation\Welcome::class,
                \Modules\Core\Filament\Pages\Documentation\Swagger::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                \Modules\Core\Filament\Widgets\WelcomeLinkWidget::class,
                \Modules\Core\Filament\Widgets\CoreStatsWidget::class,
                \Modules\Core\Filament\Widgets\CmsStatsWidget::class,
                \Modules\Core\Filament\Widgets\HorizonStatsWidget::class,
                \Modules\Core\Filament\Widgets\SystemHealthWidget::class,
                \Modules\Core\Filament\Widgets\RecentActivityWidget::class,
                \Modules\Core\Filament\Widgets\LicenseStatsWidget::class,
                \Modules\Core\Filament\Widgets\SearchEngineHealthTableWidget::class,
            ])
            ->plugins([
                ModulesPlugin::make(),
                EnvironmentIndicatorPlugin::make()
                    ->visible(true)
                    ->showBorder(false)
                    ->showGitBranch()
                    ->showDebugModeWarningInProduction(),
            ])
            // AUTHENTICATION
            ->login()
            ->profile()
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            // TODO: da attivare a sviluppi finiti
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
                LocalizationMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        return $panel;
    }
}

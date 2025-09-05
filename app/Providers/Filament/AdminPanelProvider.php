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
use Modules\Core\Http\Middleware\AdminMiddleware;

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
            // GENERAL
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn() => config('app.name') . ' ' . __('Admin'))
            // ->brandLogo('https://raw.githubusercontent.com/swolley/images/refs/heads/master/swolley-1.jpg')
            ->spa(hasPrefetching: true)
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
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
            ->plugin(ModulesPlugin::make())
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
                // DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
                // AdminMiddleware::class,
            ], isPersistent: true)
        ;

        return $panel;
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Coolsam\Modules\ModulesPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Filament\Pages\PhpInfo;
use Modules\Core\Filament\Pages\Swagger;
use Modules\Core\Filament\Pages\Welcome;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        /**
         * App panel theme CSS is built by Vite. {@see \Filament\Support\Commands\AssetsCommand} copies
         * registered styles from {@see \Filament\Support\Assets\Asset::getPath()} into the public Filament
         * asset tree; a remote placeholder path marks this entry as non-local so that step is skipped,
         * while {@see Css::html()} resolves the real URL when the panel renders.
         */
        FilamentAsset::register([
            Css::make('app-css', 'https://__vite__.invalid/app.css')->html(static fn (): string => Vite::asset('resources/css/app.css')),
            Css::make('admin-css', asset('css/admin.css')),
            Js::make('sidebar-scroll', asset('js/sidebar-scroll.js')),
            Js::make('sidebar-accordion', asset('js/sidebar-accordion.js')),
        ], 'admin');
    }

    public function panel(Panel $panel): Panel
    {
        $panel
            // GENERAL
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(static function (): string {
                $app_name = config('app.name');

                return sprintf('%s %s', is_string($app_name) ? $app_name : 'Laravel', __('Admin'));
            })
            ->brandLogo(static function (): ?string {
                $logo = config('app.logo');

                return is_string($logo) ? $logo : null;
            })
            ->spa(hasPrefetching: true)
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Green,
            ])
            ->pages([
                Pages\Dashboard::class,
                Welcome::class,
                Swagger::class,
                PhpInfo::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Health')
                    ->icon(Heroicon::OutlinedHeart),
                NavigationGroup::make()
                    ->label('Documentation')
                    ->icon(Heroicon::OutlinedDocumentText),
                NavigationGroup::make()
                    ->label('Core')
                    ->icon(Heroicon::OutlinedBolt),
                NavigationGroup::make()
                    ->label('CMS')
                    ->icon(Heroicon::OutlinedNewspaper),
                NavigationGroup::make()
                    ->label('ERP')
                    ->icon(Heroicon::OutlinedBuildingOffice),
                NavigationGroup::make()
                    ->label('AI')
                    ->icon(Heroicon::OutlinedSparkles),
            ])
            ->widgets([
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

<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Trusted proxies are configured here, not in `bootstrap/app.php`, because the
     * middleware callback of the application builder runs before the configuration
     * repository is loaded.
     */
    public function boot(): void
    {
        $trusted_proxies = config('app.trusted_proxies');

        if (! is_string($trusted_proxies) || $trusted_proxies === '') {
            return;
        }

        TrustProxies::at($trusted_proxies);

        $this->trustProxiesDuringBoot();
    }

    /**
     * Apply the trusted proxy headers to the current request while providers boot.
     *
     * The global TrustProxies middleware only runs after every provider has booted, so
     * URLs built during boot (Filament panel assets, for instance) would otherwise keep
     * the proxied scheme, which the browser then blocks as mixed content behind TLS.
     */
    private function trustProxiesDuringBoot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = $this->app->make('request');

        if (! $request instanceof Request) {
            return;
        }

        (new TrustProxies)->handle($request, static fn (Request $trusted_request): Request => $trusted_request);

        $this->app->make('url')->setRequest($request);
    }
}

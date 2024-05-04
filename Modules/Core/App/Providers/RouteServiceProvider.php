<?php

declare(strict_types=1);

namespace Modules\Core\App\Providers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Log;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     */
    protected string $moduleNamespace = 'Modules\Core\App\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware(['web'])
            ->namespace($this->moduleNamespace)
            // ->prefix('app')
            ->name('core.')
            ->group([
                module_path('Core', '/routes/dev.php'),
            ]);

        Route::middleware(['web', 'verified'])
            ->namespace($this->moduleNamespace)
            ->prefix('app')
            ->name('core.')
            ->group(module_path('Core', '/routes/web.php'));

        Route::middleware('auth')
            ->prefix('app/auth')
            ->name('core.')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Core', '/routes/auth.php'));

        Route::middleware('info')
            ->name('core.')
            ->prefix('app')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Core', '/routes/info.php'));

        // fake reset password for fortify notifications geneation. Url can be modified, but name must be 'password.reset' !!
        Route::get('/reset-password', function () {
            return abort(Response::HTTP_MOVED_PERMANENTLY);
        })->name('password.reset');
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        if (config('core.expose_crud_api')) {
            Route::prefix('api/v1')
                ->middleware(['api'])
                ->name('api.')
                ->namespace($this->moduleNamespace)
                ->group([
                    module_path('Core', '/routes/crud.php'),
                    module_path('Core', '/routes/api.php'),
                ]);
        }
    }
}

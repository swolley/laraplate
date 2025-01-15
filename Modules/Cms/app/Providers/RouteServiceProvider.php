<?php

namespace Modules\Cms\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Cms';

    protected function getPrefix(): string
    {
        return Str::slug($this->name);
    }

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
        Route::middleware('web')
            ->prefix('app')
            ->name($this->getPrefix() . '.')
            ->group([
                module_path($this->name, '/routes/web.php'),
            ]);
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        $route_prefix = 'api';
        Route::prefix("$route_prefix/v1")
            ->middleware($route_prefix)
            ->name("$name_prefix.$route_prefix.")
            ->group([
                module_path($this->name, '/routes/api.php'),
            ]);
    }
}

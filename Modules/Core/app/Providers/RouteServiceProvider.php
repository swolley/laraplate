<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Core';

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

    protected function getPrefix(): string
    {
        return Str::slug($this->name);
    }

    protected function getModuleNamespace(): string
    {
        return str_replace('Providers', 'Http\Controllers', __NAMESPACE__);
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        Route::middleware('web')
            ->namespace($this->namespace)
            ->name("$name_prefix.")
            ->group([
                module_path($this->name, '/routes/dev.php'),
            ]);

        $route_prefix = 'app';
        Route::middleware(['web'/*, 'verified'*/])
            ->namespace($this->namespace)
            ->prefix($route_prefix)
            ->name("$name_prefix.")
            ->group(module_path($this->name, '/routes/web.php'));

        Route::middleware('auth')
            ->prefix("$route_prefix/auth")
            ->name("$name_prefix.")
            ->namespace($this->namespace)
            ->group(module_path($this->name, '/routes/auth.php'));

        Route::middleware('info')
            ->name("$name_prefix.")
            ->prefix($route_prefix)
            ->namespace($this->namespace)
            ->group(module_path($this->name, '/routes/info.php'));

        // fake reset password for fortify notifications generation. Url can be modified, but name must be 'password.reset' !!
        Route::get("$route_prefix/auth/reset-password", function () {
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
            $name_prefix = $this->getPrefix();
            $route_prefix = 'api';
            Route::prefix("$route_prefix/v1")
                ->middleware([$route_prefix])
                ->name("$name_prefix.$route_prefix.")
                ->namespace($this->namespace)
                ->group([
                    module_path($this->name, '/routes/crud.php'),
                    module_path($this->name, '/routes/api.php'),
                ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Helpers\HasMergeConfigs;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Locking\LockedModelSubscriber;
use Spatie\Permission\Middleware\RoleMiddleware;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Modules\Core\Http\Middleware\ConvertStringToBoolean;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

class CoreServiceProvider extends ServiceProvider
{
    use HasMergeConfigs;

    protected string $moduleName = 'Core';

    protected string $moduleNameLower = 'core';

    protected $subscribe = [
        LockedModelSubscriber::class,
    ];

    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
        $this->registerAuths();
        $this->registerMiddlewares();

        /** @var \Illuminate\Foundation\Application $app */
        $app = $this->app;
        $is_production = $app->isProduction();

        if ($is_production && config('core.force_https')) {
            URL::forceScheme('https');
        }

        Model::preventSilentlyDiscardingAttributes(!$is_production);

        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = $this->app;

        $app->register(RouteServiceProvider::class);

        $app->singleton(Locked::class, function () {
            return new Locked();
        });

        $app->alias(Locked::class, 'locked');

        if ($app->isLocal()) {
            $app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }

    public function registerAuths(): void
    {
        // bypass all other checks if user is super admin
        Gate::before(fn(?User $user) => $user && $user->isSuperAdmin() ? true : null);
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'lang'));
        }
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace') . '\\' . $this->moduleName . '\\' . config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Register commands in the format of Command::class.
     */
    protected function registerCommands(): void
    {
        // FIXME: correggere errore inizializzazione comandi
        /* // App
        $module_commands_subpath = config('modules.paths.generator.command.path');
        $commands = $this->inspectFolderCommands($module_commands_subpath);

        // Locking
        $locking_commands_subpath = Str::replace('Console', 'Locking/Console', $module_commands_subpath);
        $locking_commands = $this->inspectFolderCommands($locking_commands_subpath);
        array_push($commands, ...$locking_commands);

        $cache_commands_subpath = Str::replace('Console', 'Cache/Console', $module_commands_subpath);
        $cache_commands = $this->inspectFolderCommands($cache_commands_subpath);
        array_push($commands, ...$cache_commands);

        $this->commands($commands); */
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $crons = [];
            $cache_key = (new CronJob())->getTable();
            if (Cache::has($cache_key)) {
                $crons = Cache::get($cache_key);
            } else if (Schema::hasTable((new CronJob())->getTable())) {
                $crons = CronJob::query()->where('is_active', true)->select(['command', 'schedule'])->get()->toArray();
                Cache::put($cache_key, $crons);
            }

            foreach ($crons as $cron) {
                $schedule->command($cron['command'])->cron($cron['schedule'])->onOneServer();
            }
        });
    }

    protected function registerMiddlewares()
    {
        $router = app('router');
        $router->middleware(LocalizationMiddleware::class);
        $router->middleware(PreviewMiddleware::class);
        $router->middleware(ConvertStringToBoolean::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    private function inspectFolderCommands(string $commandsSubpath)
    {
        $modules_namespace = config('modules.namespace');
        $files = glob(module_path($this->moduleName, $commandsSubpath . DIRECTORY_SEPARATOR . '*.php'));

        return array_map(
            fn($file) => sprintf('%s\\%s\\%s\\%s', $modules_namespace, $this->moduleName, Str::replace('/', '\\', $commandsSubpath), basename($file, '.php')),
            $files,
        );
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }
}

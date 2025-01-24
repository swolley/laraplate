<?php

namespace Modules\Cms\Providers;

use Illuminate\Support\Str;
use Modules\Cms\Models\Entity;
use Modules\Cms\Models\Content;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Cache;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Core\Overrides\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Cms';

    protected string $nameLower = 'cms';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        // $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        if (!Module::find('Core')/*->isEnabled()*/) {
            throw new \Exception('Core is required and must be enabled');
        }

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        // runtme contents aliases declarations
        $entity_cache_key = (new Entity())->getCacheKey();
        $entities = Cache::get($entity_cache_key, collect());
        if ($entities->isEmpty()) {
            $entities = Entity::query()->withoutGlobalScopes()->get();
            Cache::forever($entity_cache_key, $entities);
            Content::resolveChildTypes($entities);
        }
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $module_commands_subpath = config('modules.paths.generator.command.path');
        $commands = $this->inspectFolderCommands($module_commands_subpath);

        $this->commands($commands);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        parent::registerConfig();
    }

    /**
     * Register views.
     */
    // public function registerViews(): void
    // {
    //     $viewPath = resource_path('views/modules/' . $this->nameLower);
    //     $sourcePath = module_path($this->name, 'resources/views');

    //     $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

    //     $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

    //     $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
    //     Blade::componentNamespace($componentNamespace, $this->nameLower);
    // }

    private function inspectFolderCommands(string $commandsSubpath)
    {
        $modules_namespace = config('modules.namespace');
        $files = glob(module_path($this->name, $commandsSubpath . DIRECTORY_SEPARATOR . '*.php'));

        return array_map(
            fn($file) => sprintf('%s\\%s\\%s\\%s', $modules_namespace, $this->name, Str::replace(['app/', '/'], ['', '\\'], $commandsSubpath), basename($file, '.php')),
            $files,
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    // private function getPublishableViewPaths(): array
    // {
    //     $paths = [];
    //     foreach (config('view.paths') as $path) {
    //         if (is_dir($path . '/modules/' . $this->nameLower)) {
    //             $paths[] = $path . '/modules/' . $this->nameLower;
    //         }
    //     }

    //     return $paths;
    // }
}

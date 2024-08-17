<?php

namespace Modules\Core\App\Providers;

use Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\MigrationsEnded;

class CommandListenerProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        //
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event) {
            info("Cleaning Inspected entities");
            Cache::tags(['inspector'])->flush();
        });
    }
}

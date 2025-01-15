<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->artisan('migrate:fresh', [
            '--path' => [
                'database/migrations',
                'Modules/*/database/migrations'
            ]
        ]);

        $this->withoutExceptionHandling();
        
        // Carica automaticamente i provider dei moduli
        $this->loadModuleProviders();
    }

    protected function loadModuleProviders(): void
    {
        $modulesPath = base_path('Modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Spatie\Tags\TagsServiceProvider::class,
        ];
    }
}

<?php

namespace Modules\Cms\Tests;

use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurazioni specifiche per il modulo Cms
        $this->withoutExceptionHandling();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
            \Spatie\Tags\TagsServiceProvider::class,
            \Modules\Cms\Providers\CmsServiceProvider::class,
        ];
    }
} 
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // A test that pretends to run over HTTP (runningInConsole() false) would otherwise be
        // checked for a CSRF token, which no test request carries.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        // SchemaInspector is a process-level singleton; wipe it before each app boot so
        // memoized tables from a previous :memory: SQLite do not leak across Pest tests.
        if (class_exists(\Modules\Core\Inspector\SchemaInspector::class)) {
            \Modules\Core\Inspector\SchemaInspector::reset();
        }

        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}

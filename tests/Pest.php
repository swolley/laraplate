<?php

declare(strict_types=1);

// Pest loads CallsTerminable only at Kernel::terminate(). Preload it before
// BypassFinals wraps the file stream so a corrupted wrapper cannot break shutdown.
class_exists(Pest\Plugins\Actions\CallsTerminable::class);

DG\BypassFinals::enable();
// BypassFinals is test-runtime only: it does not edit source. Our `final`
// keywords stay in the codebase. allowPaths limits which loaded files can be
// stripped so the full suite does not OOM on vendor/.
// Prefer interfaces for doubles; add a path here only when mocking a final
// class is unavoidable (app services today, Elastic Client as third-party).
DG\BypassFinals::allowPaths([
    '*/app/*',
    '*/Modules/*/app/*',
    '*/vendor/elasticsearch/elasticsearch/src/*',
]);
DG\BypassFinals::setCacheDirectory(dirname(__DIR__) . '/storage/framework/cache/bypass-finals');

/*
| Module test configuration lives in each module's tests/Pest.php. The application
| test bootstrap only loads tests/ by default; require installed module bootstrap
| files dynamically so optional modules do not become root-level dependencies.
*/
$module_pest_files = glob(__DIR__ . '/../Modules/*/tests/Pest.php') ?: [];
sort($module_pest_files);

foreach ($module_pest_files as $module_pest_file) {
    require_once $module_pest_file;
}

/*
|--------------------------------------------------------------------------
| Test Case (application shell under tests/)
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->in(__DIR__ . '/Feature', __DIR__ . '/Unit', __DIR__ . '/Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something(): void
{
    // ..
}

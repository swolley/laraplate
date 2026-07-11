<?php

declare(strict_types=1);

DG\BypassFinals::enable();

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

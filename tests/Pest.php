<?php

declare(strict_types=1);

DG\BypassFinals::enable();

/*
| Module test configuration lives in each module's tests/Pest.php. The application
| test bootstrap only loads tests/ by default; require those files here so
| pest()->extend / uses() in modules apply when running from the repo root.
*/
require_once __DIR__ . '/../Modules/Core/tests/Pest.php';

require_once __DIR__ . '/../Modules/CMS/tests/Pest.php';

require_once __DIR__ . '/../Modules/AI/tests/Pest.php';

require_once __DIR__ . '/../Modules/ERP/tests/Pest.php';

require_once __DIR__ . '/../Modules/MES/tests/Pest.php';

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

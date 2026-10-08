<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Module API routes are loaded inside a group that already carries `api/v1`.
 *
 * A module route file that adds its own `v1` prefix publishes its routes under `api/v1/v1/...`, a URL no
 * client expects. The Shop module did exactly that. The rule this enforces: no registered route repeats
 * a version segment.
 */
it('registers API routes', function (): void {
    // Guards the guard: with no API route registered the case below would pass vacuously.
    expect(collect(Route::getRoutes()->getRoutes())->contains(
        static fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'),
    ))->toBeTrue();
});

it('keeps the version prefix out of module API route files, enabled or not', function (): void {
    // A module disabled in this environment registers no route, so the runtime check below cannot see it.
    $files = glob(base_path('Modules/*/routes/api.php')) ?: [];
    expect($files)->not->toBeEmpty();

    $offending = array_values(array_filter(
        $files,
        static fn (string $file): bool => preg_match('#prefix\(\s*[\'"]/?(api|v\d+)\b#', (string) file_get_contents($file)) === 1,
    ));

    expect($offending)->toBe([], implode(', ', array_map(static fn (string $file): string => str_replace(base_path() . '/', '', $file), $offending)));
});

it('never repeats the API version segment', function (): void {
    $repeated = collect(Route::getRoutes()->getRoutes())
        ->map(static fn (RoutingRoute $route): string => $route->uri())
        ->filter(static fn (string $uri): bool => preg_match('#(^|/)(v\d+)/\2(/|$)#', $uri) === 1)
        ->unique()
        ->values()
        ->all();

    expect($repeated)->toBe([], implode(', ', $repeated));
});

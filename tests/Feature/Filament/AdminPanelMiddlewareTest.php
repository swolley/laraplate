<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Modules\Core\Http\Middleware\AddContext;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Http\Middleware\LocalizationMiddleware;

it('applies the database settings overlay on panel page loads', function (): void {
    // The panel does not use the "web" group — it declares its own stack — so pushing
    // the overlay to web and api left /admin with no database-backed config at all.
    expect(Filament::getPanel('admin')->getMiddleware())
        ->toContain(ApplyDatabaseSettingsOverlay::class);
});

it('applies the settings overlay before resolving the locale', function (): void {
    $stack = Filament::getPanel('admin')->getMiddleware();

    // config('app.locale') is a dotted setting like any other: the overlay must not run
    // after the resolver picked the user's language.
    expect(array_search(LocalizationMiddleware::class, $stack, true))
        ->toBeGreaterThan(array_search(ApplyDatabaseSettingsOverlay::class, $stack, true));
});

it('tags panel log context as the admin surface', function (): void {
    expect(Filament::getPanel('admin')->getMiddleware())
        ->toContain(AddContext::class . ':admin');
});

it('keeps the admin log scope on livewire updates', function (): void {
    // The Livewire update route runs the "web" group, which tags requests as "app".
    // Without persistence every table sort and every modal in the backoffice would be
    // logged as if it came from the SPA.
    // The panel hands its persistent list to Livewire and clears its own copy, so this
    // is the only place the registration can be observed after boot.
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(AddContext::class . ':admin');
});

<?php

declare(strict_types=1);

use Filament\Facades\Filament;

it('keeps spa navigation enabled for the admin panel', function (): void {
    expect(Filament::getPanel('admin')->hasSpaMode())->toBeTrue();
});

it('does not prefetch admin links, so hovering a list never takes an editorial lease', function (): void {
    expect(Filament::getPanel('admin')->hasSpaPrefetching())->toBeFalse();
});

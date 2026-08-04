<?php

declare(strict_types=1);

it('overrides filament sidebar group view to keep item icons with group icons', function (): void {
    $override_path = resource_path('views/vendor/filament-panels/components/sidebar/group.blade.php');
    $override_source = file_get_contents($override_path);

    expect($override_path)->toBeFile()
        ->and($override_source)->toBeString()
        ->not->toContain('Either the group or its items can have icons, but not both')
        ->not->toContain('$itemIcon = null;')
        ->toContain('We keep both so dense');
});

it('resolves the published sidebar group view instead of the vendor package view', function (): void {
    $resolved_path = view()->getFinder()->find('filament-panels::components.sidebar.group');

    expect($resolved_path)
        ->toBe(resource_path('views/vendor/filament-panels/components/sidebar/group.blade.php'));
});

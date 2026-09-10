<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;

/**
 * @return array<int, string>
 */
function declaredAdminNavigationGroups(): array
{
    return array_values(array_filter(array_map(
        static fn (NavigationGroup $group): ?string => $group->getLabel(),
        Filament::getPanel('admin')->getNavigationGroups(),
    )));
}

/**
 * @return array<int, string>
 */
function usedAdminNavigationGroups(): array
{
    $panel = Filament::getPanel('admin');

    $groups = [];

    foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
        $group = $class::getNavigationGroup();

        if (is_string($group) && $group !== '') {
            $groups[$group] = $group;
        }
    }

    return array_values($groups);
}

it('declares every navigation group used by the panel', function (): void {
    $undeclared = array_diff(usedAdminNavigationGroups(), declaredAdminNavigationGroups());

    expect($undeclared)->toBeEmpty();
});

it('orders the navigation groups with Health and Documentation first', function (): void {
    $declared = declaredAdminNavigationGroups();

    expect(array_slice($declared, 0, 2))->toBe(['Health', 'Documentation']);

    $modules = array_slice($declared, 2);
    $sorted = $modules;
    usort($sorted, static fn (string $a, string $b): int => strcasecmp($a, $b));

    expect($modules)->toBe($sorted);
});

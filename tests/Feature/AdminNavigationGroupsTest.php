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

/**
 * The module a group belongs to: the part of its label before " - " ("ERP - Sales" is ERP), or the
 * whole label for a module's own group ("MES").
 */
function navigationGroupModule(string $label): string
{
    return explode(' - ', $label, 2)[0];
}

it('puts Health, Documentation and Core first', function (): void {
    expect(array_slice(declaredAdminNavigationGroups(), 0, 3))->toBe(['Health', 'Documentation', 'Core']);
});

it('orders the modules alphabetically and keeps the groups of a module together', function (): void {
    $modules = array_map(navigationGroupModule(...), array_slice(declaredAdminNavigationGroups(), 3));

    // Consecutive groups of one module collapse into one entry; a module listed twice is split.
    $sequence = array_values(array_filter(
        $modules,
        static fn (string $module, int $position): bool => $position === 0 || $module !== $modules[$position - 1],
        ARRAY_FILTER_USE_BOTH,
    ));
    $sorted = array_values(array_unique($sequence));
    usort($sorted, static fn (string $a, string $b): int => strcasecmp($a, $b));

    // Within a module the order is the module's own: its process, not the alphabet.
    expect($sequence)->toBe($sorted);
});

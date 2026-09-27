<?php

declare(strict_types=1);

it('registers sidebar accordion javascript for the admin panel', function (): void {
    $provider_source = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider_source)->toBeString()
        ->toContain("Js::make('sidebar-accordion', asset('js/sidebar-accordion.js'))");
});

it('patches sidebar group toggling to keep a single expanded group', function (): void {
    $accordion_js = file_get_contents(public_path('js/sidebar-accordion.js'));

    expect($accordion_js)->toBeString()
        ->toContain('toggleCollapsedGroupAccordion')
        ->toContain('getSidebarGroupLabels')
        ->toContain('alpine:initialized');
});

it('on load keeps only the active page group expanded and collapses every other group', function (): void {
    $accordion_js = file_get_contents(public_path('js/sidebar-accordion.js'));

    expect($accordion_js)->toBeString()
        ->toContain('collapseInactiveGroups')
        ->toContain('.fi-sidebar-group.fi-active[data-group-label]');
});

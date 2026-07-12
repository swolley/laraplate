<?php

declare(strict_types=1);

it('limits collapsed sidebar group dropdown height in admin css', function (): void {
    $admin_css = file_get_contents(public_path('css/admin.css'));

    expect($admin_css)->toBeString()
        ->toContain('.fi-sidebar-group .fi-dropdown-panel')
        ->toContain('max-height: min(70dvh, 28rem)')
        ->toContain('overflow-y: auto');
});

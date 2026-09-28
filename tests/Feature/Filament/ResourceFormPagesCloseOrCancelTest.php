<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;

/**
 * Create and edit pages stay on the record after saving, so their cancel button is the way out and
 * must read "Close" while nothing is unsaved. Filament offers no panel-wide hook for that label, so
 * each page carries the trait; this catches the page that was written by hand and forgot it.
 */
it('gives every create and edit page of the admin panel the close-or-cancel button', function (): void {
    $missing = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        foreach ($resource::getPages() as $registration) {
            $page = $registration->getPage();

            if (! is_subclass_of($page, EditRecord::class) && ! is_subclass_of($page, CreateRecord::class)) {
                continue;
            }

            if (! in_array(HasCloseOrCancelFormAction::class, class_uses_recursive($page), true)) {
                $missing[] = $page;
            }
        }
    }

    expect($missing)->toBe([]);
});

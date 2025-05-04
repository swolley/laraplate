<?php

declare(strict_types=1);

return [
    // Application Service Providers...
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\FilamentAuthServiceProvider::class,

    // Package Service Providers...
    Lab404\Impersonate\ImpersonateServiceProvider::class,
];

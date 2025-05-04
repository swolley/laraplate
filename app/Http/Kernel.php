<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ... existing code ...

    protected $middlewareAliases = [
        // ... existing aliases ...
        'filament.auth' => \App\Http\Middleware\FilamentAuthenticate::class,
    ];

    // ... existing code ...
}

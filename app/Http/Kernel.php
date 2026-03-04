<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Override;

final class Kernel extends HttpKernel
{
    // ... existing code ...

    #[Override]
    protected $middlewareAliases = [
        // ... existing aliases ...
    ];

    // ... existing code ...
}

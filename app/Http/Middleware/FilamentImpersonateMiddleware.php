<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

final class FilamentImpersonateMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonateManager = app(ImpersonateManager::class);

        if ($impersonateManager->isImpersonating()) {
            return redirect()->route('filament.auth.login');
        }

        return $next($request);
    }
}

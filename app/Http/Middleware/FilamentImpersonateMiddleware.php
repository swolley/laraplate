<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Lab404\Impersonate\Services\ImpersonateManager;

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

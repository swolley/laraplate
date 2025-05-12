<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Auth\Services\AuthenticationService;
use Symfony\Component\HttpFoundation\Response;

final class FilamentAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $service = app(AuthenticationService::class);
        $result = $service->authenticate($request);

        if ($result['success']) {
            if (config('auth.enable_user_licenses') && $result['license']) {
                session()->put('license_id', $result['license']->id);
            }

            return $next($request);
        }

        return redirect()->route('filament.auth.login');
    }
}

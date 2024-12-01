<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App;
use Closure;
use Illuminate\Http\Request;

class EnsureIsLocal
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!App::isLocal()) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}

<?php

namespace Modules\Core\App\Http\Middleware;

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

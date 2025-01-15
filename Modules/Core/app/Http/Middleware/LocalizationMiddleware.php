<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->lang !== App::getLocale()) {
            App::setLocale($user->lang);
        } else if (!$user) {
            $lang = $request->getPreferredLanguage();
            if ($lang) {
                $lang = Str::of($lang)->before('_')->value();
                App::setLocale($lang);
            }
        }

        return $next($request);
    }
}

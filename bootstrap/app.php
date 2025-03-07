<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->throttleWithRedis();

        // $middleware->alias([
        //     // 'auth' => \App\Http\Middleware\Authenticate::class,
        //     // 'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        //     // 'auth.session' => AuthenticateSession::class,
        //     // 'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        //     // 'can' => \Illuminate\Auth\Middleware\Authorize::class,
        //     // // 'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        //     // 'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        //     // 'precognitive' => \Illuminate\Foundation\Http\Middleware\HHandlePrecognitiveRequests::class,
        //     // 'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        //     // 'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        //     // 'hierarchical_permissions' => \Junges\ACL\Middlewares\HierarchicalPermissionsMiddleware::class
        //     // 'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        //     // 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        //     // 'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        //     // 'verified' => EnsureEmailIsVerified::class,
        // ]);

        $middleware->web(
            append: [
                EnsureFrontendRequestsAreStateful::class,
                'auth.session',
            ],
            // TODO: temporaneo da rimuovere a termine sviluppo
            remove: [
                ValidateCsrfToken::class,
                EnsureEmailIsVerified::class,
                AuthenticateSession::class,
            ]
        );

        // $middleware->api([
        //     'throttle:api',
        // ]);

        $middleware->appendToGroup('auth', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            EnsureFrontendRequestsAreStateful::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            'auth.session',
        ]);

        $middleware->appendToGroup('info', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

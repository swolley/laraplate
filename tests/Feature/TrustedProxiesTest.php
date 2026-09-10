<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    TrustProxies::flushState();

    Route::middleware('web')->get('/__scheme-probe', fn () => response()->json([
        'secure' => request()->isSecure(),
        'root' => url('/'),
    ]));
});

afterEach(function (): void {
    TrustProxies::flushState();
});

/**
 * @param  string  $trusted_proxies  value the application should trust
 */
function probeForwardedScheme(string $trusted_proxies): Illuminate\Testing\TestResponse
{
    config()->set('app.trusted_proxies', $trusted_proxies);

    (new AppServiceProvider(app()))->boot();

    return test()->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('http://laraplate.local/__scheme-probe', ['X-Forwarded-Proto' => 'https']);
}

it('honours the forwarded protocol sent by a trusted proxy', function (): void {
    $response = probeForwardedScheme('127.0.0.1,::1');

    $response->assertOk();
    expect($response->json('secure'))->toBeTrue()
        ->and($response->json('root'))->toStartWith('https://');
});

it('ignores the forwarded protocol sent by an untrusted address', function (): void {
    $response = probeForwardedScheme('10.0.0.1');

    $response->assertOk();
    expect($response->json('secure'))->toBeFalse();
});

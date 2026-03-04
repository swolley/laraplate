<?php

declare(strict_types=1);

use App\Http\Controllers\AppController;
use App\Http\Middleware\RequireWebAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'home'])->name('app.home');

Route::middleware([RequireWebAuth::class])
    ->name('app.')
    ->group(static function (): void {
        Route::get('/modules/{module}', [AppController::class, 'module'])->name('module');
        Route::get('/modules/{module}/models/{model}', [AppController::class, 'model'])->name('model');
    });

Route::get('/login', static function (Request $request) {
    $host = $request->getHost();
    $referer = (string) $request->headers->get('referer', '');

    if (str_starts_with($host, 'admin.') || str_contains($referer, '/admin')) {
        return to_route('filament.admin.auth.login');
    }

    return redirect()->to('/');
})->name('login');

<?php

declare(strict_types=1);

use App\Http\Controllers\AppController;
use App\Http\Middleware\RequireWebAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'home'])->name('app.home');

Route::middleware([RequireWebAuth::class])
    ->name('app.')
    ->group(static function (): void {
        Route::get('/modules/{module}', [AppController::class, 'module'])->name('module');
        Route::get('/modules/{module}/models/{model}', [AppController::class, 'model'])->name('model');
    });

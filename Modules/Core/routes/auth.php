<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\UserController;

Route::controller(UserController::class)->name('auth.')->group(function (): void {
    Route::get('/user/profile-information', 'userInfo')->withoutMiddleware('auth')->name('userInfo');
    Route::post('/impersonate', 'impersonate')->can('impersonate')->name('impersonate');
    Route::post('/leave-impersonate', 'leaveImpersonate')->can('impersonate')->name('leaveImpersonate');
    // Route::patch('/configs', 'updateConfigs')->can('edit')->name('updateConfigs');
});

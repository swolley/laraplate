<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\UserController;

Route::controller(UserController::class)->name('auth.')->group(function (): void {
    Route::get('/user/profile-information', 'userInfo')->withoutMiddleware('auth')->name('userInfo');
    Route::post('/impersonate', 'impersonate')->can('impersonate')->name('impersonate');
    Route::post('/leave-impersonate', 'leaveImpersonate')->can('impersonate')->name('leaveImpersonate');
    // Route::patch('/configs', 'updateConfigs')->can('edit')->name('updateConfigs');
    Route::get('/still-here', 'maintainSession')->name('maintainSession');

    if (config('auth.enable_social_login')) {
        $social_services = 'facebook|twitter|twitter-oauth-2|linkedin-openid|google|github|gitlab|bitbucket|slack|slack-openid';
        Route::get('/{service}/redirect', 'socialLoginRedirect')->where('service', $social_services);
        Route::get('/{service}/callback', 'socialLoginCallback')->where('service', $social_services);
    }
});

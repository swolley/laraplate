<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\SettingController;

Route::controller(SettingController::class)->name('info.')->group(function (): void {
    Route::get('/info', 'siteInfo')->name('siteInfo');
    Route::get('/configs', 'getSiteConfigs')->name('getSiteConfigs');
    Route::get('/translations/{lang?}', 'getTranslations')->where('lang', '[a-z]{2}(?:[-_][A-Z]{2})?')->name('translations');
});

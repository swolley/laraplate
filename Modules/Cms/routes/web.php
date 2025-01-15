<?php

use Illuminate\Support\Facades\Route;
use Modules\Cms\Http\Controllers\LocationsController;
// use Modules\Cms\Http\Controllers\CmsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('locations')->group(function () {
    Route::get('/geocode', [LocationsController::class, 'geocode'])->name('locations.geocode');
});
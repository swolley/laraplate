<?php

use Illuminate\Support\Facades\Route;
use Modules\Cms\Http\Controllers\ContentsController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

// Route::group(['prefix' => 'locations'], function () {
//     Route::get('/{value}/contents', [ContentsController::class, 'getContentsByLocation'])->name('locations.contents');
//     Route::get('/{value}/{entity}', [ContentsController::class, 'getEntityContentsByLocation'])->name('locations.entity');
// });
Route::group(['prefix' => '{relation}'], function () {
    $entities = ['contents', ...Cache::get('entities')->pluck('name')->toArray()];
    $entities = array_map(fn($entity) => Str::plural($entity), $entities);
    Route::get('/{value}/{entity}', [ContentsController::class, 'getContentsByRelation'])->name('relation.contents')->where('relation', 'tags|categories|locations|authors')->where('entity', $entities);
});


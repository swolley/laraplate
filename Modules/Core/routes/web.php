<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\CrudController;
use Modules\Core\App\Http\Controllers\GridsController;

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

Route::name('crud.')->prefix('/crud')->group(function () {
	require(__DIR__ . '/crud.php');

	Route::controller(CrudController::class)->group(function () {
		Route::patch('/lock/{entity}', 'lock')->name('lock');
		Route::patch('/unlock/{entity}', 'unlock')->name('unlock');
		Route::patch('/approve/{entity}', 'approve')->name('approve');
		Route::patch('/disapprove/{entity}', 'disapprove')->name('disapprove');
		Route::patch('/activate/{entity}', 'activate')->name('activate');
		Route::patch('/inactivate/{entity}', 'inactivate')->name('inactivate');
		Route::delete('/cache-clear/{entity}', 'clearModelCache')->name('cache-clear');
	});

	Route::controller(GridsController::class)->prefix('grid')->group(function () {
		Route::get('/get-configs/{entity?}', 'getGridsConfigs')->name('grids.getGridsConfigs');
		Route::match(['get', 'post', 'patch', 'delete'], '/{entity}', 'grid')->name('grids.grid');
	});
});

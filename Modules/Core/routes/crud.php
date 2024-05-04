<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\CrudController;

Route::controller(CrudController::class)->group(function () {
	Route::get('/select/{entity}', 'list')->name('list');
	Route::get('/detail/{entity}', 'detail')->name('detail');
	Route::get('/tree/{entity}', 'tree')->name('tree');
	Route::get('/history/{entity}', 'history')->name('history');
	Route::post('/insert/{entity}', 'insert')->name('insert');
	Route::patch('/update/{entity}', 'update')->name('replace');
	Route::delete('/delete/{entity}', 'delete')->name('delete');
});

// Route::controller(GridsController::class)->prefix('grid')->group(function () {
// 	Route::get('/get-configs/{entity?}', 'getGridsConfigs')->name('grids.getGridsConfigs');
// 	Route::match(['get', 'post', 'patch', 'delete'], '/{entity}', 'grid')->name('grids.grid');
// });

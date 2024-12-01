<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CrudController;

Route::controller(CrudController::class)->group(function () {
	Route::match(['get', 'post'], '/select/{entity}', 'list')->name('list');
	Route::get('/detail/{entity}', 'detail')->name('detail');
	Route::get('/tree/{entity}', 'tree')->name('tree');
	Route::match(['get', 'post'], '/search/{entity?}', 'search')->name('search');
	Route::get('/history/{entity}', 'history')->name('history');
	Route::post('/insert/{entity}', 'insert')->name('insert');
	Route::match(['patch', 'put'], '/update/{entity}', 'update')->name('replace');
	Route::match(['delete', 'post'], '/delete/{entity}', 'delete')->name('delete');
});

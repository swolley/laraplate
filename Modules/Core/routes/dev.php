<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\DocsController;

Route::controller(DocsController::class)->name('docs.')->group(function (): void {
	if (App::isLocal()) {
		Route::get('/', 'welcome')->name('welcome');
		Route::get('/phpinfo', 'phpinfo')->name('phpinfo');
	}

	Route::get('docs/{filename}', 'mergeDocs')->name('swaggerDocs')->where('filename', 'v\d');
});

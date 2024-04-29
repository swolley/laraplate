<?php

use Modules\Core\App\Http\Controllers\DocsController;

Route::controller(DocsController::class)->name('docs.')->group(function (): void {
	if (App::isLocal()) {
		Route::get('/', 'welcome')->name('welcome');
		Route::get('/phpinfo', 'phpinfo')->name('phpinfo');
	}
	Route::get('app/swagger/{filename}', 'mergeDocs')->name('swaggerDocs');
});

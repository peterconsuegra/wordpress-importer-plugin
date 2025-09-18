<?php

//php artisan route:list

//Route::get('import_wordpress', 'Pete\WordPressImporter\Http\WordPressImporterController@create')->middleware(['web']);

//Route::post('import_wordpress/store', 'Pete\WordPressImporter\Http\WordPressImporterController@store')->middleware(['web']);

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pete\WordPressImporter\Http\WordPressImporterController as WpiController;
use Pete\WordPressImporter\Http\ChunkUploadController;

Route::middleware(['web'])->group(function () {
    Route::get('/wordpress-importer', [WpiController::class, 'create'])->name('wpimport.create');
    Route::post('/wordpress-importer', [WpiController::class, 'store'])->name('wpimport.store');
    Route::get('/wordpress-importer/status/{id}', [WpiController::class, 'status'])->name('wpimport.status');

    // point to WpiController (since upload/abort live there)
    Route::post('/wordpress-importer/upload-chunk', [WpiController::class, 'upload'])->name('wpimport.chunk.upload');
    Route::delete('/wordpress-importer/upload-chunk/abort', [WpiController::class, 'abort'])->name('wpimport.chunk.abort');
});



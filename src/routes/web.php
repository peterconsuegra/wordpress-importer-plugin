<?php

//php artisan route:list

//Route::get('import_wordpress', 'Pete\WordPressImporter\Http\WordPressImporterController@create')->middleware(['web']);

//Route::post('import_wordpress/store', 'Pete\WordPressImporter\Http\WordPressImporterController@store')->middleware(['web']);

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pete\WordPressImporter\Http\WordPressImporterController as WpiController;

Route::middleware(['web'])
    ->prefix('wordpress-importer')
    ->name('wpi.')
    ->group(function (): void {
        // Show the import form
        Route::get('/', [WpiController::class, 'create'])->name('create');

        // Handle the import submission
        Route::post('/', [WpiController::class, 'store'])->name('store');
    });

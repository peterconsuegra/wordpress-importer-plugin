<?php

//php artisan route:list

Route::get('import_wordpress', 'Pete\WordPressImporter\Http\WordPressImporterController@create')->middleware(['web']);

Route::post('import_wordpress/store', 'Pete\WordPressImporter\Http\WordPressImporterController@store')->middleware(['web']);


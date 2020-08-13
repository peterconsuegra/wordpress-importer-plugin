<?php


Route::get('import_wordpress', 'Pete\WordPressImporter\Http\WordPressImporterController@create');

Route::post('import_wordpress/store', 'Pete\WordPressImporter\Http\WordPressImporterController@store');


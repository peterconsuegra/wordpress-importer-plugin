<?php

namespace Pete\WordPressImporter;

use Illuminate\Support\ServiceProvider;
use Log;

class WordPressImporterServiceProvider extends ServiceProvider
{
    public function register()
    {
		
        $this->app->singleton('wordpress-importer-plugin', function ($app) {
            return new WordPressImporter;
        });	
		
    }

    public function boot()
    {
       
		$this->loadRoutesFrom(__DIR__.'/routes/web.php');
		$this->loadViewsFrom(__DIR__.'/views', 'wordpress-importer-plugin');
		
    }
}

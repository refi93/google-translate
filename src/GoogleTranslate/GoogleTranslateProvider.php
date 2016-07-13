<?php namespace Dedicated\GoogleTranslate;

use Illuminate\Support\ServiceProvider;

class GoogleTranslateProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('ddctd143/google-translate', 'google-translate', __DIR__ .'/..');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

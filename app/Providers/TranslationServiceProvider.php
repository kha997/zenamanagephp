<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Load translations for all available locales
        $locales = config('app.locales', ['en']);
        
        foreach ($locales as $locale) {
            $this->loadTranslationsFrom(resource_path("lang/{$locale}"), $locale);
        }
        
        // Set default locale
        App::setLocale(config('app.locale', 'en'));
    }
}

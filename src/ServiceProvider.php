<?php

namespace LaravelCommandKit;

use Illuminate\Support\ServiceProvider;

class CommandKitServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;
    
    /**
     * Command kit
     */
    protected $commands = [
        'LaravelCommandKit\Commands\MicroAppCommand',
    ];

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->handleConfigs();
        // $this->handleMigrations();
        // $this->handleViews();
        // $this->handleTranslations();
        // $this->handleRoutes();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->commands);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function handleConfigs()
    {
        $configPath = __DIR__ . '/../config/command-kit.php';

        $this->publishes([$configPath => config_path('command-kit.php')]);

        $this->mergeConfigFrom($configPath, 'command-kit');
    }

    private function handleTranslations()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'command-kit');
    }

    private function handleViews()
    {
        $this->loadViewsFrom(__DIR__.'/../views', 'command-kit');

        $this->publishes([__DIR__.'/../views' => base_path('resources/views/vendor/command-kit')]);
    }

    private function handleMigrations()
    {
        $this->publishes([__DIR__ . '/../migrations' => base_path('database/migrations')]);
    }

    private function handleRoutes()
    {
        include __DIR__.'/../routes/routes.php';
    }
}

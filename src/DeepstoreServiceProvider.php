<?php

namespace HeliosLive\Deepstore;

use Illuminate\Support\ServiceProvider;
use HeliosLive\Deepstore\Commands\StoreCommand;

class DeepstoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/deepstore.php' => config_path('deepstore.php'),
            ], 'deepstore-config');

            $this->commands([
                StoreCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/deepstore.php', 'deepstore');
    }
}

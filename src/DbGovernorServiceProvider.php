<?php

namespace Mamun724682\DbGovernor;

use Illuminate\Support\ServiceProvider;

class DbGovernorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db-governor.php', 'db-governor');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'db-governor');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/db-governor.php' => config_path('db-governor.php'),
        ], 'db-governor-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/db-governor'),
        ], 'db-governor-views');
    }
}



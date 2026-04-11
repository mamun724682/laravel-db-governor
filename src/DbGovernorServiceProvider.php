<?php

namespace Mamun724682\DbGovernor;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Mamun724682\DbGovernor\Http\Middleware\DbGovernanceAccess;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class DbGovernorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db-governor.php', 'db-governor');

        $this->app->singleton(AccessGuard::class);
        $this->app->singleton(ConnectionManager::class);
    }

    public function boot(): void
    {
        Route::aliasMiddleware('db-governor.auth', DbGovernanceAccess::class);

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



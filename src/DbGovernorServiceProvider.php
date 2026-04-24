<?php

namespace Mamun724682\DbGovernor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mamun724682\DbGovernor\Http\Middleware\DbGovernanceAccess;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;
use Mamun724682\DbGovernor\Services\DryRunEngine;
use Mamun724682\DbGovernor\Services\RiskAnalyzer;

class DbGovernorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db-governor.php', 'db-governor');

        $this->app->singleton(AccessGuard::class);
        $this->app->singleton(ConnectionManager::class);

        $this->app->bind(RiskAnalyzer::class, function ($app) {
            return new RiskAnalyzer(
                blockedPatterns: config('db-governor.blocked_patterns', []),
                flaggedPatterns: config('db-governor.flagged_patterns', []),
                maxAffectedRows: (int) config('db-governor.max_affected_rows', 1000),
                dryRun: $app->make(DryRunEngine::class),
            );
        });
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

        View::composer('db-governor::*', function ($view) {
            $request = request();
            $guard = app(AccessGuard::class);

            try {
                $guardEmail = $guard->email();
                $guardRole = $guard->role();
            } catch (\Throwable) {
                $guardEmail = null;
                $guardRole = null;
            }

            $view->with([
                'token' => $request->route('token'),
                'currentConnection' => $request->route('connection'),
                'tokenBaseUrl' => $request->route('token')
                    ? url(config('db-governor.path', 'db-governor').'/'.$request->route('token'))
                    : null,
                'guardEmail' => $guardEmail,
                'guardRole' => $guardRole,
            ]);
        });
    }
}

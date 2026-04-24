<?php

use Illuminate\Support\Facades\Route;
use Mamun724682\DbGovernor\Http\Controllers\AuthController;
use Mamun724682\DbGovernor\Http\Controllers\ConnectionController;
use Mamun724682\DbGovernor\Http\Controllers\DashboardController;
use Mamun724682\DbGovernor\Http\Controllers\QueryController;
use Mamun724682\DbGovernor\Http\Controllers\SchemaController;
use Mamun724682\DbGovernor\Http\Controllers\SqlController;
use Mamun724682\DbGovernor\Http\Controllers\TableController;

$prefix = config('db-governor.path', 'db-governor');
$middleware = config('db-governor.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Public login routes
        Route::get('/login', [AuthController::class, 'showLogin'])
            ->name('db-governor.login');
        Route::post('/login', [AuthController::class, 'login'])
            ->name('db-governor.login.submit');

        // Auth-protected routes
        Route::middleware('db-governor.auth')->group(function () {
            // Logout
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('db-governor.logout');

            // Connection picker
            Route::get('/', [ConnectionController::class, 'pick'])
                ->name('db-governor.connections.pick');

            // Connection-scoped routes
            Route::prefix('/{connection}')->group(function () {
                Route::get('/', [DashboardController::class, 'index'])
                    ->name('db-governor.dashboard');

                Route::get('/queries', [QueryController::class, 'index'])
                    ->name('db-governor.queries');

                Route::post('/queries', [QueryController::class, 'store'])
                    ->name('db-governor.queries.store');

                Route::post('/queries/{query}/{action}', [QueryController::class, 'action'])
                    ->name('db-governor.queries.action');

                Route::post('/sql', [SqlController::class, 'execute'])
                    ->name('db-governor.sql.execute');

                Route::get('/schema/{table}', [SchemaController::class, 'table'])
                    ->name('db-governor.schema.table');

                Route::get('/{table}', [TableController::class, 'show'])
                    ->name('db-governor.table.show');
            });
        });

        // Catch-all: redirect malformed/mismatched paths to login
        Route::any('/{any}', fn () => redirect()->route('db-governor.login'))
            ->where('any', '.*');
    });

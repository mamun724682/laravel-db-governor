<?php

use Illuminate\Support\Facades\Route;
use Mamun724682\DbGovernor\Http\Controllers\AuthController;
use Mamun724682\DbGovernor\Http\Controllers\ConnectionController;

$prefix     = config('db-governor.path', 'db-governor');
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
            Route::get('/{token}/{connection}/', fn () => response('ok'))
                ->name('db-governor.dashboard');

            Route::get('/{token}', [ConnectionController::class, 'pick'])
                ->name('db-governor.connections.pick');
        });

        // Catch-all: redirect malformed/mismatched paths to login
        Route::any('/{any}', fn () => redirect()->route('db-governor.login'))
            ->where('any', '.*');
    });

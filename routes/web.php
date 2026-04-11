<?php

use Illuminate\Support\Facades\Route;

$prefix     = config('db-governor.path', 'db-governor');
$middleware = config('db-governor.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Public login route
        Route::get('/login', fn () => response('login'))
            ->name('db-governor.login');

        // Auth-protected routes
        Route::middleware('db-governor.auth')->group(function () {
            Route::get('/{token}/{connection}/', fn () => response('ok'))
                ->name('db-governor.dashboard');

            Route::get('/{token}', fn () => response('ok'))
                ->name('db-governor.connections');
        });

        // Catch-all: redirect malformed/mismatched paths (e.g. tokens with '/') to login
        Route::any('/{any}', fn () => redirect()->route('db-governor.login'))
            ->where('any', '.*');
    });

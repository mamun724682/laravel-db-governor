<?php

use Illuminate\Support\ServiceProvider;
use Mamun724682\DbGovernor\DbGovernorServiceProvider;

it('config is publishable with db-governor-config tag', function () {
    $publishes = ServiceProvider::pathsToPublish(
        DbGovernorServiceProvider::class,
        'db-governor-config'
    );
    expect($publishes)->not->toBeEmpty();
});

it('views are not publishable (intentionally kept internal)', function () {
    $publishes = ServiceProvider::pathsToPublish(
        DbGovernorServiceProvider::class,
        'db-governor-views'
    );
    expect($publishes)->toBeEmpty();
});

it('migrations are loaded from the package', function () {
    $files = glob(__DIR__.'/../../database/migrations/*.php');
    expect($files)->not->toBeEmpty();
    expect(collect($files)->filter(fn ($f) => str_contains($f, 'dbg_queries'))->count())->toBe(1);
});

it('config file exists and has required keys', function () {
    expect(config('db-governor.path'))->toBeString();
    expect(config('db-governor.allowed'))->toBeArray();
    expect(config('db-governor.connections'))->toBeArray();
    expect(config('db-governor.blocked_patterns'))->toBeArray();
    expect(config('db-governor.flagged_patterns'))->toBeArray();
    expect(config('db-governor.max_affected_rows'))->toBeNumeric();
});

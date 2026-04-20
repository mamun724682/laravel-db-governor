<?php

use Mamun724682\DbGovernor\DbGovernorServiceProvider;

it('loads the db-governor service provider', function () {
    expect(app()->getProviders(DbGovernorServiceProvider::class))
        ->not->toBeEmpty();
});

it('merges the db-governor config', function () {
    expect(config('db-governor'))->toBeArray();
    expect(config('db-governor.path'))->toBe('db-governor');
    expect(config('db-governor.table_name'))->toBe('dbg_queries');
});

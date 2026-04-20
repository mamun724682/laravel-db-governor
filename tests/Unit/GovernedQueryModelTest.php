<?php

use Mamun724682\DbGovernor\Models\GovernedQuery;

it('uses configurable table name', function () {
    config(['db-governor.table_name' => 'dbg_queries']);
    $model = new GovernedQuery;
    expect($model->getTable())->toBe('dbg_queries');
});

it('uses configurable governance connection', function () {
    config(['db-governor.governance_connection' => 'custom_conn']);
    $model = new GovernedQuery;
    expect($model->getConnectionName())->toBe('custom_conn');
});

it('falls back to default connection when governance_connection is null', function () {
    config(['db-governor.governance_connection' => null]);
    $model = new GovernedQuery;
    expect($model->getConnectionName())->toBe(config('database.default'));
});

it('has no auto-incrementing id', function () {
    $model = new GovernedQuery;
    expect($model->incrementing)->toBeFalse();
    expect($model->getKeyType())->toBe('string');
});

it('casts risk_flags as array', function () {
    $model = new GovernedQuery;
    expect($model->getCasts())->toHaveKey('risk_flags');
});

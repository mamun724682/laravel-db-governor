<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Drivers\SqliteInspector;
use Mamun724682\DbGovernor\Services\ConnectionManager;
use Mamun724682\DbGovernor\Services\DryRunEngine;

beforeEach(function () {
    config(['db-governor.connections' => ['main' => 'sqlite']]);
    $this->engine = app(DryRunEngine::class);
});

it('returns null when dry_run_enabled is false', function () {
    config(['db-governor.dry_run_enabled' => false]);
    expect($this->engine->estimate('UPDATE users SET x=1 WHERE id=1', 'main'))->toBeNull();
});

it('returns an integer or null for a valid connection', function () {
    config(['db-governor.dry_run_enabled' => true]);
    $result = $this->engine->estimate('UPDATE users SET x=1 WHERE id=1', 'main');
    expect($result)->toBeNull(); // no 'users' table in test db
});

it('delegates to ConnectionManager inspector', function () {
    $manager = Mockery::mock(ConnectionManager::class);
    $inspector = Mockery::mock(SqliteInspector::class);
    $conn = DB::connection('sqlite');

    $manager->shouldReceive('inspector')->with('main')->andReturn($inspector);
    $manager->shouldReceive('resolve')->with('main')->andReturn($conn);
    $inspector->shouldReceive('estimateAffectedRows')->andReturn(42);

    config(['db-governor.dry_run_enabled' => true]);
    $engine = new DryRunEngine($manager);
    expect($engine->estimate('UPDATE t SET x=1 WHERE id=1', 'main'))->toBe(42);
});

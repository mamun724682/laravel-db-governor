<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections'       => ['main' => 'sqlite'],
    ]);
});

it('table browser shows column headers and filter UI', function () {
    DB::connection('sqlite')
        ->statement('CREATE TABLE IF NOT EXISTS view_test (id INTEGER PRIMARY KEY, name TEXT)');
    DB::connection('sqlite')
        ->table('view_test')->insert(['id' => 1, 'name' => 'Alice']);

    $guard = app(\Mamun724682\DbGovernor\Services\AccessGuard::class);
    $token = $guard->login('admin@test.com');

    $this->get(route('db-governor.table.show', [
        'token'      => $token,
        'connection' => 'main',
        'table'      => 'view_test',
    ]))->assertOk()
       ->assertSee('name')
       ->assertSee('Alice')
       ->assertSee('Filter');

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS view_test');
});

it('table browser extends layout', function () {
    DB::connection('sqlite')
        ->statement('CREATE TABLE IF NOT EXISTS view_test2 (id INTEGER PRIMARY KEY, label TEXT)');

    $guard = app(\Mamun724682\DbGovernor\Services\AccessGuard::class);
    $token = $guard->login('admin@test.com');

    $this->get(route('db-governor.table.show', [
        'token'      => $token,
        'connection' => 'main',
        'table'      => 'view_test2',
    ]))->assertOk()
       ->assertSee('DB Governance');

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS view_test2');
});


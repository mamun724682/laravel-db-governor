<?php

use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections'    => ['main' => 'sqlite'],
        'db-governor.path'           => 'db-governor',
        'db-governor.hidden_tables'  => [],
    ]);

    $guard       = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

it('dashboard no longer contains the SQL console textarea', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // The SQL console should NOT be on the dashboard
    expect($html)->not->toContain('db-governor.sql.execute');
});

it('dashboard still shows analytics stat cards', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Analytics summary cards must still be present
    expect($html)->toContain('Pending')
        ->toContain('Approved')
        ->toContain('Executed');
});

it('queries page has an Open SQL Console button', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('SQL Console');
});

it('queries page contains the SQL execution endpoint', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('db-governor.sql.execute');
});


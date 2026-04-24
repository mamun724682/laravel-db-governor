<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => [],
        'db-governor.blocked_patterns' => [],
        'db-governor.flagged_patterns' => [],
        'db-governor.dry_run_enabled' => false,
        'db-governor.log_read_queries' => false,
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');
});

it('returns validation error JSON for empty sql string', function () {
    $response = $this->postJson(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => '   ']
    );

    $response->assertStatus(422);
});

it('returns success false with error for a non-SQL string', function () {
    $response = $this->post(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => 'not a sql query at all']
    )->assertOk()->json();

    expect($response['success'])->toBeFalse();
    expect($response['error'])->toBeString();
    expect($response['error'])->not->toBeEmpty();
});

it('returns success false with a meaningful error for a SQL syntax error', function () {
    $response = $this->post(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => 'SELECT FROM WHERE']
    )->assertOk()->json();

    expect($response['success'])->toBeFalse();
    expect($response['error'])->toBeString();
});

it('returns success true for a valid SELECT query', function () {
    $response = $this->post(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => 'SELECT 1']
    )->assertOk()->json();

    expect($response['success'])->toBeTrue();
});

it('queries page console Run button is disabled when textarea is empty', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // The Run button must use :disabled="loading || !sql.trim()" (already exists — assert it)
    expect($html)->toContain('!sql.trim()');
});

<?php

use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections'    => ['main' => 'sqlite'],
        'db-governor.path'           => 'db-governor',
    ]);

    $guard       = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

it('queries JSON data includes connection field', function () {
    GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE t SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Pending->value, 'submitted_by' => 'dev@test.com',
    ]);

    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // The JS data embedded in the page must have the connection field
    expect($html)->toContain('modal.connection');
});

it('queries page embeds snapshot fields in modal data', function () {
    GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE t SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Executed->value, 'submitted_by' => 'dev@test.com',
        'executed_by' => 'admin@test.com', 'executed_at' => now(),
        'query_table' => 'users', 'snapshot_primary_key' => 'id',
        'snapshot_strategy' => 'row_snapshot', 'snapshot_size_bytes' => 1024,
        'snapshot_data' => json_encode([['id' => 1]]),
    ]);

    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('query_table')
        ->toContain('snapshot_primary_key')
        ->toContain('snapshot_strategy')
        ->toContain('snapshot_size_bytes');
});

it('rollback button is rendered for executed query with snapshot', function () {
    GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE t SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Executed->value, 'submitted_by' => 'dev@test.com',
        'executed_by' => 'admin@test.com', 'executed_at' => now(),
        'snapshot_data' => json_encode([['id' => 1]]),
    ]);

    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // The rollback form or button must be conditionally rendered when snapshot_data exists
    expect($html)->toContain('rollback');
    expect($html)->toContain('modal.snapshot_data');
});

it('queries page shows no snapshot message when snapshot is null', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Should have a conditional for no-snapshot state
    expect($html)->toContain('snapshot_data');
});


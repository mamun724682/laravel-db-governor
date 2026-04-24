<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
    ]);

    // One write query
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET active=0 WHERE id=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    // One DDL query
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'ALTER TABLE users ADD COLUMN bio TEXT',
        'query_type' => 'ddl',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    // One read query (audit log)
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'SELECT * FROM users',
        'query_type' => 'read',
        'risk_level' => 'low',
        'status' => QueryStatus::Executed->value,
        'submitted_by' => 'dev@test.com',
        'executed_by' => 'dev@test.com',
        'executed_at' => now(),
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');
});

// ── default tab (write) ───────────────────────────────────────────────────

it('default tab shows only write and ddl queries (not read)', function () {
    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    $types = $queries->pluck('query_type')->unique()->values()->all();
    expect($types)->not->toContain('read');
    expect($queries->count())->toBe(2);
});

it('?tab=write shows only write and ddl queries', function () {
    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'tab' => 'write',
    ]))->assertOk()->viewData('queries');

    foreach ($queries as $q) {
        expect($q->query_type)->toBeIn(['write', 'ddl', 'unknown']);
    }
    expect($queries->count())->toBe(2);
});

it('?tab=read shows only read queries', function () {
    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'tab' => 'read',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->query_type)->toBe('read');
});

it('?tab=read does not show write or ddl queries', function () {
    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'tab' => 'read',
    ]))->assertOk()->viewData('queries');

    $types = $queries->pluck('query_type')->unique()->values()->all();
    expect($types)->not->toContain('write');
    expect($types)->not->toContain('ddl');
});

// ── $tab view variable ────────────────────────────────────────────────────

it('tab variable is passed to the view', function () {
    $tab = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'tab' => 'read',
    ]))->assertOk()->viewData('tab');

    expect($tab)->toBe('read');
});

it('tab defaults to write when not specified', function () {
    $tab = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('tab');

    expect($tab)->toBe('write');
});

// ── tab navigation in HTML ────────────────────────────────────────────────

it('queries page HTML contains a tab=write link', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tab=write');
});

it('queries page HTML contains a tab=read link', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tab=read');
});

it('active tab link is visually distinguished in HTML', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'tab' => 'read',
    ]))->assertOk()->getContent();

    // The read tab must be marked active (e.g. border-b-2, font-semibold, font-bold, or aria-current)
    expect($html)->toMatch('/border-b-2|font-semibold|font-bold|aria-current/');
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
    ]);

    for ($i = 1; $i <= 30; $i++) {
        GovernedQuery::create([
            'connection' => 'main',
            'sql_raw' => "UPDATE users SET active=0 WHERE id={$i}",
            'name' => "Query {$i}",
            'description' => 'Batch update',
            'query_type' => 'write',
            'risk_level' => 'low',
            'status' => QueryStatus::Pending->value,
            'submitted_by' => 'dev@test.com',
        ]);
    }

    $guard = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

it('query log uses simplePaginate with 25 items per page', function () {
    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk();

    $queries = $response->viewData('queries');
    expect($queries->count())->toBe(25);
    expect($queries->hasMorePages())->toBeTrue();
});

it('keyword filter searches sql_raw', function () {
    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
        'keyword' => 'WHERE id=1',
    ]))->assertOk();

    $queries = $response->viewData('queries');
    // Only "WHERE id=1" and "WHERE id=10", "WHERE id=11" ... all containing "id=1"
    foreach ($queries as $q) {
        expect($q->sql_raw)->toContain('id=1');
    }
});

it('keyword filter searches name', function () {
    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
        'keyword' => 'Query 5',
    ]))->assertOk();

    $queries = $response->viewData('queries');
    expect($queries->count())->toBeGreaterThanOrEqual(1);
});

it('status filter returns only matching status rows', function () {
    // Update one row to approved
    GovernedQuery::first()->update(['status' => QueryStatus::Approved->value]);

    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
        'status' => QueryStatus::Approved->value,
    ]))->assertOk();

    $queries = $response->viewData('queries');
    expect($queries->count())->toBe(1);
    expect($queries->first()->status)->toBe(QueryStatus::Approved->value);
});

it('date_from filter excludes older rows', function () {
    GovernedQuery::query()->update(['created_at' => now()->subDays(5)]);
    GovernedQuery::first()->update(['created_at' => now()]);

    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
        'date_from' => now()->subDay()->toDateString(),
    ]))->assertOk();

    $queries = $response->viewData('queries');
    expect($queries->count())->toBe(1);
});

it('date_to filter excludes future rows', function () {
    GovernedQuery::query()->update(['created_at' => now()->addDays(5)]);
    GovernedQuery::first()->update(['created_at' => now()->subDays(3)]);

    $response = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
        'date_to' => now()->toDateString(),
    ]))->assertOk();

    $queries = $response->viewData('queries');
    expect($queries->count())->toBe(1);
});

<?php

use Illuminate\Support\Facades\DB;
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

    // Create a table with 30 rows
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS paginate_test (id INTEGER PRIMARY KEY, val TEXT)'
    );
    for ($i = 1; $i <= 30; $i++) {
        DB::connection('sqlite')->table('paginate_test')->insert(['id' => $i, 'val' => "row{$i}"]);
    }
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS paginate_test');
});

// ── pagination ─────────────────────────────────────────────────────────────

it('returns at most 25 rows on page 1 by default', function () {
    $response = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
    ]))->assertOk();

    $paginator = $response->viewData('paginator');
    expect(count($paginator->items()))->toBe(25);
});

it('page 2 returns the remaining 5 rows', function () {
    $response = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'page'       => 2,
    ]))->assertOk();

    $paginator = $response->viewData('paginator');
    expect(count($paginator->items()))->toBe(5);
});

it('page 1 hasMorePages when total rows exceed 25', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
    ]))->assertOk()->viewData('paginator');

    expect($paginator->hasMorePages())->toBeTrue();
});

it('page 2 does not have more pages when all rows consumed', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'page'       => 2,
    ]))->assertOk()->viewData('paginator');

    expect($paginator->hasMorePages())->toBeFalse();
});

it('page 1 on a table with exactly 25 rows has no more pages', function () {
    // Remove 5 rows so only 25 remain
    DB::connection('sqlite')->table('paginate_test')->where('id', '>', 25)->delete();

    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(25);
    expect($paginator->hasMorePages())->toBeFalse();
});

it('page 1 on a table with fewer than 25 rows shows all rows and no more pages', function () {
    DB::connection('sqlite')->table('paginate_test')->where('id', '>', 10)->delete();

    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(10);
    expect($paginator->hasMorePages())->toBeFalse();
});

// ── columns ────────────────────────────────────────────────────────────────

it('passes correct columns to the view', function () {
    $columns = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
    ]))->assertOk()->viewData('columns');

    expect(array_column($columns, 'name'))
        ->toContain('id')
        ->toContain('val');
});

// ── sorting ────────────────────────────────────────────────────────────────

it('sort by id DESC puts the highest id first on page 1', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'sort'       => 'id',
        'dir'        => 'desc',
    ]))->assertOk()->viewData('paginator');

    expect($paginator->items()[0]['id'])->toBe(30);
});

it('sort by id ASC puts the lowest id first on page 1', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'sort'       => 'id',
        'dir'        => 'asc',
    ]))->assertOk()->viewData('paginator');

    expect($paginator->items()[0]['id'])->toBe(1);
});

it('invalid sort column is ignored and returns results without error', function () {
    $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'sort'       => 'nonexistent_column',
        'dir'        => 'asc',
    ]))->assertOk();
});

// ── filters ────────────────────────────────────────────────────────────────

it('filter with equality returns only matching rows', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [['col' => 'id', 'op' => '=', 'val' => '1']],
        ],
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(1);
    expect($paginator->items()[0]['id'])->toBe(1);
});

it('filter with invalid column name is silently ignored', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [['col' => 'nonexistent_col', 'op' => '=', 'val' => 'x']],
        ],
    ]))->assertOk()->viewData('paginator');

    // Invalid column is ignored → full result set returned
    expect(count($paginator->items()))->toBe(25);
});

it('multiple AND conditions in same group narrow results', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [
                ['col' => 'id', 'op' => '>=', 'val' => '1'],
                ['col' => 'id', 'op' => '<=', 'val' => '3'],
            ],
        ],
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(3);
});

it('OR groups (multiple groups) combine results', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [['col' => 'id', 'op' => '=', 'val' => '1']],
            [['col' => 'id', 'op' => '=', 'val' => '2']],
        ],
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(2);
});

it('LIKE filter returns matching rows', function () {
    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [['col' => 'val', 'op' => 'LIKE', 'val' => 'row1%']],
        ],
    ]))->assertOk()->viewData('paginator');

    // row1, row10, row11, ..., row19 → 11 rows
    expect(count($paginator->items()))->toBe(11);
});

it('IS NULL filter returns only null-valued rows', function () {
    DB::connection('sqlite')->table('paginate_test')->where('id', 1)->update(['val' => null]);

    $paginator = $this->get(route('db-governor.table.show', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'paginate_test',
        'f'          => [
            [['col' => 'val', 'op' => 'IS NULL', 'val' => '']],
        ],
    ]))->assertOk()->viewData('paginator');

    expect(count($paginator->items()))->toBe(1);
    expect($paginator->items()[0]['id'])->toBe(1);
});


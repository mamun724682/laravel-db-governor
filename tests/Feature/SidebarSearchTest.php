<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => [],
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, title TEXT)'
    );
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS articles');
});

it('dashboard page renders a sidebar table search input', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('table-search');
});

it('queries page renders a sidebar table search input', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('table-search');
});

it('sidebar search input has an Alpine x-model binding', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tableSearch');
});

it('sidebar table list items have an x-show filter expression', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tableSearch');
    expect($html)->toContain('toLowerCase()');
});

// ── Active state indicators ───────────────────────────────────────────────────

it('dashboard nav link shows active indicator when on dashboard', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // Active nav item uses indigo background + semibold
    expect($html)->toContain('bg-indigo-50 text-indigo-700 font-semibold');
});

it('query log nav link shows active indicator when on queries page', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('bg-indigo-50 text-indigo-700 font-semibold');
});

it('dashboard nav link is not active when on queries page', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // Only one active item; the inactive ones use hover classes
    expect($html)->toContain('hover:bg-indigo-50 hover:text-indigo-700');
});

it('active table shows indigo accent bar indicator in sidebar', function () {
    $html = $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'articles',
    ]))->assertOk()->getContent();

    // Active table has indigo pill bar and highlighted text
    expect($html)
        ->toContain('bg-indigo-500')
        ->toContain('text-indigo-700 font-semibold');
});

it('inactive tables do not show the active indicator bar', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // On dashboard no table is active, so no indigo bar should appear
    expect($html)->not->toContain('bg-indigo-500');
});

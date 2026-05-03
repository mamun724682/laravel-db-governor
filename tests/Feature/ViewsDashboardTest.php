<?php

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections' => ['main' => 'sqlite'],
    ]);
});

it('dashboard shows stats cards', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Executed');
});

it('dashboard shows all six stat labels', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Approved')
        ->assertSee('Executed')
        ->assertSee('Rejected')
        ->assertSee('Blocked');
});

it('write modal markup is present on queries page', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.queries', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('writeModal', false)
        ->assertSee('Submit for Approval');
});

it('dashboard uses localStorage to show recently visited tables instead of all tables', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $html = $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->getContent();

    // Alpine component reads from localStorage using the connection-specific key
    expect($html)
        ->toContain('dbg_recent_main')
        ->toContain('localStorage.getItem')
        ->toContain('recentTables');
});

it('dashboard shows recently visited tables section heading', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('Recently Visited Tables');
});

it('dashboard does not render a static server-side table list', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $html = $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->getContent();

    // The table list is now driven by Alpine/localStorage, not a Blade @foreach
    // So the grid list should only exist inside an x-for template, not static PHP HTML
    expect($html)->not->toMatch('/<li>\s*<a[^>]+db-governor\.table\.show[^>]+>\s*🗄/');
});

it('dashboard empty state message shown when no tables visited', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $html = $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('No tables visited yet');
});

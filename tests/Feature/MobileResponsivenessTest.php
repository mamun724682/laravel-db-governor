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
    ]);

    $guard = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

// ── Layout: hamburger & sidebar toggle ────────────────────────────────────────

it('layout renders a hamburger menu button for mobile', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)
        ->toContain('sidebarOpen')
        ->toContain('Toggle sidebar');
});

it('layout hamburger button is hidden on large screens', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('lg:hidden');
});

it('layout contains mobile overlay that closes sidebar on click', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)
        ->toContain('sidebarOpen = false')
        ->toContain('bg-black/40');
});

it('layout sidebar uses fixed positioning on mobile with transform transition', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)
        ->toContain('lg:static')
        ->toContain('lg:translate-x-0')
        ->toContain('-translate-x-full')
        ->toContain('transition-transform');
});

it('layout sidebar Alpine binding toggles translate class based on sidebarOpen', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain("sidebarOpen ? 'translate-x-0' : '-translate-x-full'");
});

// ── Layout: sticky header ─────────────────────────────────────────────────────

it('layout header is sticky', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('sticky top-0');
});

it('layout header has a z-index to stay above sidebar overlay', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('z-30');
});

// ── Layout: x-cloak ──────────────────────────────────────────────────────────

it('layout includes x-cloak style to prevent flash of unstyled content', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('[x-cloak]');
});

// ── Layout: responsive padding ────────────────────────────────────────────────

it('layout main content uses responsive padding', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('sm:p-6');
});

// ── Layout: user email hidden on mobile ───────────────────────────────────────

it('layout user email is hidden on mobile with sm:inline', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Email span uses hidden sm:inline so it only shows on sm+ screens
    expect($html)->toContain('hidden sm:inline');
});

// ── Queries page: modals have mx-4 for mobile padding ─────────────────────────

it('queries detail modal container has mx-4 for mobile edge spacing', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Both the detail modal and SQL console modal should have mx-4
    expect(substr_count($html, 'mx-4'))->toBeGreaterThanOrEqual(2);
});

it('write modal container has mx-4 for mobile edge spacing', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('max-w-lg mx-4');
});

// ── Queries page: responsive query builder grids ──────────────────────────────

it('query builder WHERE grid uses responsive columns', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('grid-cols-1 sm:grid-cols-3');
});

it('query builder does not use fixed non-responsive 3-column grid', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Should not have a bare grid-cols-3 without responsive prefix
    expect($html)->not->toContain('"grid grid-cols-3');
});

// ── Queries page: responsive filter form ─────────────────────────────────────

it('query log filter form uses responsive grid layout on mobile', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('grid-cols-1 sm:grid-cols-2');
});


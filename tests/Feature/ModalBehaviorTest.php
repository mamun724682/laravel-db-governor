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

// ── queries detail modal ───────────────────────────────────────────────────

it('queries modal backdrop has a click handler to close modal', function () {
    $html = $this->get(route('db-governor.queries', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // The backdrop div must handle click to close (either @click.self or @click on backdrop + @click.stop inside)
    expect($html)->toContain('@click="modal = null"')
        ->toContain('@click.stop');
});

it('queries modal backdrop has keyboard ESC handler', function () {
    $html = $this->get(route('db-governor.queries', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('@keydown.escape.window="modal = null"');
});

it('queries modal inner content stops click propagation to backdrop', function () {
    $html = $this->get(route('db-governor.queries', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // Inner modal div must stop click bubbling so only backdrop clicks close it
    expect($html)->toContain('@click.stop');
});

// ── write-modal ────────────────────────────────────────────────────────────

it('write modal backdrop has a click handler to close modal', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // Backdrop div must handle @click to close the write modal
    expect($html)->toContain('@click="writeModal = false"');
});

it('write modal backdrop has keyboard ESC handler', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('@keydown.escape.window="writeModal = false"');
});

it('write modal inner content stops click propagation to backdrop', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('@click.stop');
});

// ── write modal form action URL ────────────────────────────────────────────

it('write modal form action does not contain the string "undefined"', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->not->toContain('/undefined/');
    expect($html)->not->toContain("'undefined'");
});

it('write modal form action contains the current token', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token'      => $this->token,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain($this->token);
});


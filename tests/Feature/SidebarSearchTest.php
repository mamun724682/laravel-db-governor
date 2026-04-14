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

it('dashboard page renders a sidebar table search input', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('table-search');
});

it('queries page renders a sidebar table search input', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('table-search');
});

it('sidebar search input has an Alpine x-model binding', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tableSearch');
});

it('sidebar table list items have an x-show filter expression', function () {
    $html = $this->get(route('db-governor.dashboard', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('tableSearch');
    expect($html)->toContain('toLowerCase()');
});


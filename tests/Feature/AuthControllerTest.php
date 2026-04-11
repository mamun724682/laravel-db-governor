<?php

use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections'       => ['main' => 'sqlite'],
        'db-governor.path'              => 'db-governor',
    ]);
});

it('shows the login page', function () {
    $this->get(route('db-governor.login'))
        ->assertOk()
        ->assertViewIs('db-governor::login');
});

it('redirects to connection picker on valid email', function () {
    $this->post(route('db-governor.login.submit'), ['email' => 'admin@test.com'])
        ->assertRedirect();
});

it('redirects back with error on unknown email', function () {
    $this->post(route('db-governor.login.submit'), ['email' => 'nobody@test.com'])
        ->assertRedirect(route('db-governor.login'))
        ->assertSessionHas('error');
});

it('auto-redirects to dashboard when only one connection', function () {
    $token = app(AccessGuard::class)->login('admin@test.com');
    $this->get(route('db-governor.connections.pick', ['token' => $token]))
        ->assertRedirect();
});


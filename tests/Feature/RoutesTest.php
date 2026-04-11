<?php

use Illuminate\Support\Facades\Route;

it('login GET route exists', function () {
    $this->get(route('db-governor.login'))->assertOk();
});

it('login POST route returns redirect on failed validation', function () {
    $this->post(route('db-governor.login.submit'), [])->assertRedirect();
});

it('all 9 routes are registered', function () {
    $routes = collect(Route::getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter(fn ($name) => str_starts_with($name ?? '', 'db-governor.'));

    expect($routes->values()->all())
        ->toContain('db-governor.login')
        ->toContain('db-governor.login.submit')
        ->toContain('db-governor.connections.pick')
        ->toContain('db-governor.dashboard')
        ->toContain('db-governor.queries')
        ->toContain('db-governor.queries.store')
        ->toContain('db-governor.queries.action')
        ->toContain('db-governor.sql.execute')
        ->toContain('db-governor.table.show');
});

it('view composer shares token and connection with db-governor views', function () {
    config([
        'db-governor.allowed.admins'    => ['admin@example.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections'       => [
            'primary'   => 'sqlite',
            'secondary' => 'sqlite',
        ],
    ]);

    $guard = app(\Mamun724682\DbGovernor\Services\AccessGuard::class);
    $token = $guard->login('admin@example.com');

    $response = $this->get(route('db-governor.connections.pick', ['token' => $token]));
    $response->assertOk();
    $response->assertViewHas('token', $token);
    $response->assertViewHas('currentConnection', null);
});

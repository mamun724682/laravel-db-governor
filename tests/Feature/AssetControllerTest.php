<?php

use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections'       => ['main' => 'sqlite'],
    ]);
});

it('serves alpine.min.js with correct content-type', function () {
    /** @var TestResponse $response */
    $response = $this->get(route('db-governor.assets', 'alpine.min.js'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');

    expect($response->getContent())->not->toBeEmpty();
});

it('serves tailwind.js with correct content-type', function () {
    /** @var TestResponse $response */
    $response = $this->get(route('db-governor.assets', 'tailwind.js'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/javascript');

    expect($response->getContent())->not->toBeEmpty();
});

it('returns 404 for unknown asset files', function () {
    $this->get(route('db-governor.assets', 'malicious.php'))
        ->assertNotFound();
});

it('assets route requires no authentication', function () {
    // No session / login — assets must be publicly accessible
    $this->get(route('db-governor.assets', 'alpine.min.js'))
        ->assertOk();
});

it('asset responses include long-lived cache headers', function () {
    $response = $this->get(route('db-governor.assets', 'alpine.min.js'));

    $cacheControl = $response->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
});

it('asset responses include an etag header', function () {
    $response = $this->get(route('db-governor.assets', 'alpine.min.js'));

    expect($response->headers->get('ETag'))->not->toBeEmpty();
});

it('layout does not reference cdn.tailwindcss.com', function () {
    $this->loginAsGuard('admin@test.com');

    $html = $this->get(route('db-governor.dashboard', ['connection' => 'main']))->getContent();

    expect($html)
        ->not->toContain('cdn.tailwindcss.com')
        ->not->toContain('cdn.jsdelivr.net')
        ->toContain(route('db-governor.assets', 'tailwind.js'))
        ->toContain(route('db-governor.assets', 'alpine.min.js'));
});

it('login page does not reference any CDN', function () {
    $html = $this->get(route('db-governor.login'))->getContent();

    expect($html)
        ->not->toContain('cdn.tailwindcss.com')
        ->not->toContain('cdn.jsdelivr.net')
        ->toContain(route('db-governor.assets', 'tailwind.js'));
});





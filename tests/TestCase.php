<?php

namespace Mamun724682\DbGovernor\Tests;

use Mamun724682\DbGovernor\DbGovernorServiceProvider;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DbGovernorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * Log in a user via the db-governor session mechanism.
     * Stores the token in the test session so the middleware accepts requests.
     *
     * @return string The raw cache token (useful for cache assertions).
     */
    protected function loginAsGuard(string $email): string
    {
        $token = app(AccessGuard::class)->login($email);
        $this->withSession(['dbg_token' => $token]);
        return $token;
    }
}

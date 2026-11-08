<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->ensureTestDatabase($app);

        return $app;
    }

    /**
     * Stop before RefreshDatabase runs migrate:fresh on anything but the in-memory SQLite test database.
     * A cached config (bootstrap/cache/config.php) makes Laravel ignore phpunit.xml, so the tests would
     * otherwise wipe the real MySQL database from .env.
     */
    private function ensureTestDatabase($app): void
    {
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:' && $app->environment('testing')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests against the "%s" database on the "%s" connection (environment "%s"). '
            .'Tests must use in-memory SQLite. Run "php artisan config:clear" (or "composer test") and try again.',
            $database,
            $connection,
            $app->environment(),
        ));
    }
}

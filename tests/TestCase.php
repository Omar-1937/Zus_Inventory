<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only database the automated tests are ever allowed to touch.
     * Tests wipe and rebuild it, so it must never be the development database.
     */
    public const TEST_DATABASE = 'zus_coffee_inventory_shadow';

    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.connections.mysql.database');

        if ($app['config']->get('database.default') !== 'mysql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against database [%s]. Tests may only use [%s]. Run `php artisan config:clear` if the configuration is cached.',
                $database,
                self::TEST_DATABASE,
            ));
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}

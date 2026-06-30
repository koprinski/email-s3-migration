<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pin the suite to the test database and the sync queue — the container injects
     * DB_DATABASE/QUEUE_CONNECTION into the env, which phpunit <env> can't override.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.pgsql.database', 'emails_test');
        $app['config']->set('queue.default', 'sync');

        return $app;
    }
}

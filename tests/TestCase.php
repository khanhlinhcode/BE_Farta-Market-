<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $driver = config('database.default');
        $database = (string) config("database.connections.{$driver}.database");
        $isolated = $driver === 'mysql'
            ? preg_match('/^sivi_(test|e2e)_[a-z0-9_]+$/', $database)
                && config('database.connections.mysql.host') === '127.0.0.1'
                && (int) config('database.connections.mysql.port') !== 3306
            : $driver === 'sqlite' && ($database === ':memory:'
                || $database === 'database/testing.sqlite'
                || $database === base_path('database/testing.sqlite')
                || str_starts_with(basename(dirname($database)), 'sivi-e2e-'));
        if (! $app->environment('testing') || ! $isolated) {
            throw new \RuntimeException('Refusing database tests outside an isolated testing database.');
        }

        return $app;
    }
}

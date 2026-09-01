<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use VimaTech\SecureFields\Providers\SecureFieldsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SecureFieldsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('secure-fields.key', base64_encode(random_bytes(32)));
        $app['config']->set('secure-fields.hashing.key', bin2hex(random_bytes(16)));
        $app['config']->set('database.migrations.update_date_on_publish', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}

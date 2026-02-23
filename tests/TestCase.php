<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Xfa\Pdf\XfaPdfServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            XfaPdfServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'XfaPdf' => \Xfa\Pdf\Facades\XfaPdf::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}

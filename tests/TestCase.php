<?php

namespace Rekuest\ArtifactDeployer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rekuest\ArtifactDeployer\ArtifactDeployerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ArtifactDeployerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('artifact-deployer.enabled', true);
        $app['config']->set('artifact-deployer.allowed_environments', []);
        $app['config']->set('artifact-deployer.auth.key', 'test-secret-key');
        $app['config']->set('artifact-deployer.maintenance.enabled', false);
    }
}

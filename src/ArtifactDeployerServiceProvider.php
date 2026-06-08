<?php

namespace Rekuest\ArtifactDeployer;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rekuest\ArtifactDeployer\Console\PackCommand;
use Rekuest\ArtifactDeployer\Console\RunCommand;
use Rekuest\ArtifactDeployer\Console\StatusCommand;
use Rekuest\ArtifactDeployer\Http\Controllers\RunController;
use Rekuest\ArtifactDeployer\Http\Middleware\VerifyKey;

class ArtifactDeployerServiceProvider extends ServiceProvider
{
    public static string $name = 'artifact-deployer';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/'.static::$name.'.php',
            static::$name,
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/'.static::$name.'.php' => config_path(static::$name.'.php'),
        ], static::$name.'-config');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunCommand::class,
                StatusCommand::class,
                PackCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        $prefix = config('artifact-deployer.route.prefix', 'artifact-deployer');
        $middleware = config('artifact-deployer.route.middleware', ['api']);

        // The deploy route MUST stay reachable while the app is in maintenance
        // mode (no secret required), otherwise once a deploy puts the site down
        // the CI could never re-trigger it. Merges with any consumer exclusions.
        if (class_exists(PreventRequestsDuringMaintenance::class)) {
            PreventRequestsDuringMaintenance::except(trim($prefix, '/').'/*');
        }

        Route::prefix($prefix)
            ->middleware(array_merge($middleware, [VerifyKey::class]))
            ->group(function () {
                Route::post('/run', [RunController::class, 'run'])->name('rad.run');
            });
    }
}

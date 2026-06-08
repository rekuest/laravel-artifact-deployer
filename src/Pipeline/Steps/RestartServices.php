<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Illuminate\Support\Facades\Artisan;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;

/**
 * Restarts long-running workers at the end of the deploy so they pick up the
 * new code: queue:restart and, optionally, horizon:terminate.
 */
class RestartServices extends AbstractStep
{
    public function name(): string
    {
        return 'restart_services';
    }

    protected function run(DeployContext $ctx): string
    {
        if ($ctx->dryRun) {
            return 'dry-run: skipped';
        }

        $done = [];

        if (config('artifact-deployer.restart.queue', true)) {
            Artisan::call('queue:restart');
            $done[] = 'queue:restart';
        }

        if (config('artifact-deployer.restart.horizon', false)) {
            Artisan::call('horizon:terminate');
            $done[] = 'horizon:terminate';
        }

        return $done === [] ? 'nothing to restart' : implode(', ', $done);
    }
}

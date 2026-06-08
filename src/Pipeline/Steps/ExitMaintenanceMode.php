<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Illuminate\Support\Facades\Artisan;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Throwable;

/**
 * Brings the app back up (`artisan up`). NON-fatal: a failure is logged but
 * does not throw. Run on success, and on failure only when
 * maintenance.keep_down_on_failure = false.
 */
class ExitMaintenanceMode extends AbstractStep
{
    public function name(): string
    {
        return 'exit_maintenance';
    }

    protected function run(DeployContext $ctx): string
    {
        if (! config('artifact-deployer.maintenance.enabled', true)) {
            return 'maintenance disabled (skipped)';
        }

        if ($ctx->dryRun) {
            return 'dry-run: skipped';
        }

        try {
            Artisan::call('up');
            $ctx->maintenanceActive = false;

            return 'site back online';
        } catch (Throwable $e) {
            return 'WARNING: up failed (non-fatal): '.$e->getMessage();
        }
    }
}

<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Illuminate\Support\Facades\Artisan;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Throwable;

/**
 * Puts the app into maintenance mode. Idempotent and NON-fatal: if `down`
 * fails it logs a warning but does not abort the deploy (maintenance is a
 * protection, not a prerequisite). See package docs §2.9.1.
 */
class EnterMaintenanceMode extends AbstractStep
{
    public function name(): string
    {
        return 'enter_maintenance';
    }

    protected function run(DeployContext $ctx): string
    {
        if (! $ctx->maintenance || ! config('artifact-deployer.maintenance.enabled', true)) {
            return 'maintenance disabled (skipped)';
        }

        if ($ctx->dryRun) {
            return 'dry-run: skipped';
        }

        try {
            $options = ['--retry' => (int) config('artifact-deployer.maintenance.retry', 30)];
            $secret = config('artifact-deployer.maintenance.secret');
            if (! empty($secret)) {
                $options['--secret'] = $secret;
            }

            Artisan::call('down', $options);
            $ctx->maintenanceActive = true;

            return 'site in maintenance';
        } catch (Throwable $e) {
            return 'WARNING: down failed (non-fatal): '.$e->getMessage();
        }
    }
}

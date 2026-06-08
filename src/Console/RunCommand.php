<?php

namespace Rekuest\ArtifactDeployer\Console;

use Illuminate\Console\Command;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\DeployPipeline;

class RunCommand extends Command
{
    protected $signature = 'rad:run
        {--artifact= : Path to the artifact (overrides config)}
        {--sha= : Expected SHA-256 of the artifact}
        {--no-maintenance : Skip maintenance mode (deploy hot)}
        {--dry-run : Verify and validate without writing/migrating}';

    protected $description = 'Run the artifact deploy pipeline (same as the HTTP endpoint).';

    public function handle(DeployPipeline $pipeline): int
    {
        if (! config('artifact-deployer.enabled', true)) {
            $this->error('Artifact deployer is disabled (RAD_ENABLED=false).');

            return self::FAILURE;
        }

        $ctx = new DeployContext(
            deployId: DeployContext::makeId(),
            artifactPath: $this->option('artifact') ?: null,
            sha256: $this->option('sha') ?: null,
            dryRun: (bool) $this->option('dry-run'),
            maintenance: ! $this->option('no-maintenance'),
        );

        $this->info("Deploy {$ctx->deployId} started".($ctx->dryRun ? ' (dry-run)' : ''));

        try {
            $pipeline->run($ctx);
        } catch (DeployException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($ctx->steps as $step) {
            $line = sprintf('  [%s] %s (%dms)', $step['status'], $step['name'], $step['duration_ms']);
            $step['status'] === 'ok' ? $this->line($line) : $this->error($line);
            if ($step['output'] !== '') {
                $this->line('      '.str_replace("\n", "\n      ", $step['output']));
            }
        }

        if ($ctx->status === 'success') {
            $this->info("Deploy {$ctx->deployId} succeeded in {$ctx->durationMs}ms.");

            return self::SUCCESS;
        }

        $this->error("Deploy {$ctx->deployId} FAILED: {$ctx->error}");
        $this->warn('No automatic rollback. Recovery: re-deploy the previous artifact.');

        return self::FAILURE;
    }
}

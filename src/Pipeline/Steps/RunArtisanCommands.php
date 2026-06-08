<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Illuminate\Support\Facades\Artisan;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\StepInterface;

/**
 * Runs the configurable post-deploy command pipeline in a FIXED order:
 *   1. package:discover
 *   2. migrate --force
 *   3. custom[] (in declaration order)
 *   4. optimize:clear
 * Each toggle is read from config('artifact-deployer.commands').
 */
class RunArtisanCommands implements StepInterface
{
    public function name(): string
    {
        return 'run_artisan';
    }

    public function handle(DeployContext $ctx): void
    {
        // Running migrations / seeders mutates state: past this point a failure
        // must not auto-recover the maintenance window.
        if (! $ctx->dryRun) {
            $ctx->mutationStarted = true;
        }

        foreach ($this->commands() as [$command, $options]) {
            $start = microtime(true);

            if ($ctx->dryRun) {
                $ctx->recordStep("run_artisan:{$command}", 'ok', 'dry-run: skipped', $start);

                continue;
            }

            $exit = Artisan::call($command, $options);
            $output = Artisan::output();
            $ctx->recordStep("run_artisan:{$command}", 'ok', $output, $start);

            if ($exit !== 0) {
                throw DeployException::server("Artisan command '{$command}' exited with code {$exit}.");
            }
        }
    }

    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    private function commands(): array
    {
        $config = (array) config('artifact-deployer.commands', []);
        $commands = [];

        if (($config['package_discover'] ?? true)) {
            $commands[] = ['package:discover', []];
        }

        if (($config['migrate'] ?? true)) {
            $commands[] = ['migrate', ['--force' => true]];
        }

        foreach ((array) ($config['custom'] ?? []) as $custom) {
            if (is_string($custom)) {
                $commands[] = [$custom, []];
            } elseif (is_array($custom) && isset($custom[0])) {
                $commands[] = [$custom[0], (array) ($custom[1] ?? [])];
            }
        }

        if (($config['optimize_clear'] ?? true)) {
            $commands[] = ['optimize:clear', []];
        }

        return $commands;
    }
}

<?php

namespace Rekuest\ArtifactDeployer\Pipeline;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Rekuest\ArtifactDeployer\Events\DeployFailed;
use Rekuest\ArtifactDeployer\Events\DeployStarted;
use Rekuest\ArtifactDeployer\Events\DeployStepCompleted;
use Rekuest\ArtifactDeployer\Events\DeploySucceeded;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Pipeline\Steps\DisposeArtifact;
use Rekuest\ArtifactDeployer\Pipeline\Steps\ExitMaintenanceMode;
use Rekuest\ArtifactDeployer\Pipeline\Steps\ExtractArtifact;
use Rekuest\ArtifactDeployer\Pipeline\Steps\PreMigrateDump;
use Rekuest\ArtifactDeployer\Pipeline\Steps\RestartServices;
use Rekuest\ArtifactDeployer\Pipeline\Steps\RunArtisanCommands;
use Rekuest\ArtifactDeployer\Pipeline\Steps\VerifyArtifact;
use Rekuest\ArtifactDeployer\Support\DeployLock;
use Throwable;

class DeployPipeline
{
    public function __construct(protected ?DeployLock $lock = null) {}

    /**
     * Run the full deploy. Acquires a single-flight lock, executes the ordered
     * steps, emits events and persists the final status. Never throws for a
     * step failure (the outcome lives in the returned context); only throws
     * DeployException(409) when the lock cannot be acquired.
     */
    public function run(DeployContext $ctx): DeployContext
    {
        $lock = $this->lock ?? new DeployLock('rad:run', (int) config('artifact-deployer.timeout', 600));

        if (! $lock->acquire()) {
            throw DeployException::conflict('A deploy is already in progress.');
        }

        $this->log('info', 'Deploy started', $ctx);
        DeployStarted::dispatch($ctx);

        try {
            foreach ($this->steps() as $step) {
                $this->log('info', "Step started: {$step->name()}", $ctx);
                $step->handle($ctx);
                DeployStepCompleted::dispatch($ctx, $step);
            }

            (new ExitMaintenanceMode)->handle($ctx);
            $ctx->succeed();
            $this->log('info', 'Deploy succeeded', $ctx);
            DeploySucceeded::dispatch($ctx);
        } catch (Throwable $e) {
            $ctx->recordStep('failed', 'error', $e->getMessage(), microtime(true));
            $ctx->fail($e);

            // Bring the site back up if either the operator opted out of keeping
            // it down, OR the failure happened before any mutation (artifact not
            // found, bad/invalid archive, checksum mismatch): the app is intact,
            // so there is no reason to leave it in maintenance.
            $keepDown = config('artifact-deployer.maintenance.keep_down_on_failure', true);
            if (! $keepDown || ! $ctx->mutationStarted) {
                (new ExitMaintenanceMode)->handle($ctx);
            }

            // Stack trace stays server-side only.
            Log::channel($this->channel())->error('Deploy failed', [
                'deploy_id' => $ctx->deployId,
                'exception' => (string) $e,
            ]);

            DeployFailed::dispatch($ctx, $e);
        } finally {
            $ctx->finish();
            $lock->release();
            $this->persistStatus($ctx);
        }

        return $ctx;
    }

    /** @return array<int, StepInterface> */
    protected function steps(): array
    {
        return [
            // Read-only checks first: nothing below runs (and the site is never
            // taken down) unless the artifact is present, intact and openable.
            new VerifyArtifact,
            // ExtractArtifact enters maintenance at its point of no return, right
            // before the first byte is written (see ExtractArtifact).
            new ExtractArtifact,
            new PreMigrateDump,
            new RunArtisanCommands,
            new RestartServices,
            // ExitMaintenanceMode + DisposeArtifact run on success (see run()).
        ];
    }

    protected function persistStatus(DeployContext $ctx): void
    {
        // Dispose the artifact only on success.
        if ($ctx->status === 'success') {
            try {
                (new DisposeArtifact)->handle($ctx);
            } catch (Throwable $e) {
                $this->log('warning', 'Artifact disposal failed: '.$e->getMessage(), $ctx);
            }
        }

        $dir = storage_path('app/'.trim((string) config('artifact-deployer.state_path', 'artifact-deployer'), '/'));
        File::ensureDirectoryExists($dir);
        File::put($dir.'/last.json', json_encode($ctx->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function log(string $level, string $message, DeployContext $ctx): void
    {
        Log::channel($this->channel())->{$level}($message, ['deploy_id' => $ctx->deployId]);
    }

    protected function channel(): string
    {
        return (string) config('artifact-deployer.log_channel', 'stack');
    }
}

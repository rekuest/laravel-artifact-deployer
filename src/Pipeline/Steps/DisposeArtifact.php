<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Support\ArtifactConfig;

/**
 * Applies artifact.after on the artifact (and its .sha256 sidecar) after a
 * successful deploy: rename (suffix) | delete | keep.
 */
class DisposeArtifact extends AbstractStep
{
    public function name(): string
    {
        return 'dispose_artifact';
    }

    protected function run(DeployContext $ctx): string
    {
        if ($ctx->dryRun) {
            return 'dry-run: skipped';
        }

        $after = (string) config('artifact-deployer.artifact.after', 'rename');
        $path = ArtifactConfig::path($ctx);

        if (! is_file($path)) {
            return 'artifact already absent (skipped)';
        }

        return match ($after) {
            'delete' => $this->delete($path),
            'keep' => 'artifact kept in place',
            default => $this->rename($path),
        };
    }

    private function delete(string $path): string
    {
        @unlink($path);
        $this->disposeSidecar('delete');

        return 'artifact deleted';
    }

    private function rename(string $path): string
    {
        $suffix = (string) config('artifact-deployer.artifact.rename_suffix', '.deployed');
        @rename($path, $path.$suffix);
        $this->disposeSidecar('rename', $suffix);

        return 'artifact renamed to '.basename($path).$suffix;
    }

    private function disposeSidecar(string $action, string $suffix = ''): void
    {
        $sidecar = ArtifactConfig::checksumPath();
        if ($sidecar === null || ! is_file($sidecar)) {
            return;
        }

        if ($action === 'delete') {
            @unlink($sidecar);
        } elseif ($action === 'rename') {
            @rename($sidecar, $sidecar.$suffix);
        }
    }
}

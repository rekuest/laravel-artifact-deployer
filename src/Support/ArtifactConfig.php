<?php

namespace Rekuest\ArtifactDeployer\Support;

use Rekuest\ArtifactDeployer\Pipeline\DeployContext;

/**
 * Resolves the effective artifact settings for a run, honouring per-run
 * overrides carried by the context (HTTP body / CLI options) over config.
 */
final class ArtifactConfig
{
    public static function path(DeployContext $ctx): string
    {
        $override = $ctx->artifactPath;

        if ($override !== null && $override !== '') {
            // Absolute path: trust it (CLI / operator). Relative: anchor under base_path.
            if (str_starts_with($override, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $override)) {
                return $override;
            }

            return SafePath::resolveWithin(base_path(), $override);
        }

        return (string) config('artifact-deployer.artifact.path', base_path('build_artifact.zip'));
    }

    public static function format(): string
    {
        return (string) config('artifact-deployer.artifact.format', 'zip');
    }

    public static function extractTo(): string
    {
        return (string) config('artifact-deployer.artifact.extract_to', base_path());
    }

    /** @return array<int, string> */
    public static function protectedPaths(): array
    {
        return (array) config('artifact-deployer.artifact.protected_paths', []);
    }

    public static function checksumPath(): ?string
    {
        $path = config('artifact-deployer.artifact.checksum_path');

        return $path !== null ? (string) $path : null;
    }
}

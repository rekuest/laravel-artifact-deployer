<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Rekuest\ArtifactDeployer\Extraction\ExtractorFactory;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Support\ArtifactConfig;

/**
 * Securely extracts the artifact over base_path() using the chosen extractor,
 * with anti path-traversal + protected-paths guards on every entry.
 */
class ExtractArtifact extends AbstractStep
{
    public function name(): string
    {
        return 'extract_artifact';
    }

    protected function run(DeployContext $ctx): string
    {
        $path = ArtifactConfig::path($ctx);
        $destination = ArtifactConfig::extractTo();

        if ($ctx->dryRun) {
            // Still validate the archive is openable without writing anything.
            $extractor = ExtractorFactory::make(ArtifactConfig::format());
            $extractor->assertReadable($path);

            return 'dry-run: extraction skipped (archive validated)';
        }

        $extractor = ExtractorFactory::make(ArtifactConfig::format());
        $count = $extractor->extract(
            $path,
            $destination,
            ArtifactConfig::protectedPaths(),
            // Point of no return: the whole archive index has been validated and
            // the first byte is about to be written. Only NOW do we enter
            // maintenance and mark the deploy as mutating — so any earlier check
            // failure (missing file, bad checksum, path-traversal, corrupt entry)
            // never takes the site down.
            function () use ($ctx) {
                $ctx->mutationStarted = true;
                (new EnterMaintenanceMode)->handle($ctx);
            },
        );

        return "{$count} files extracted";
    }
}

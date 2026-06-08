<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Extraction\ExtractorFactory;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Support\ArtifactConfig;

/**
 * Verifies, before extracting anything:
 *   - the PHP extension required by the chosen format is available;
 *   - the artifact file exists;
 *   - the archive is openable and its index readable (always);
 *   - the SHA-256 checksum matches, when provided or when require_checksum=true.
 */
class VerifyArtifact extends AbstractStep
{
    public function name(): string
    {
        return 'verify_artifact';
    }

    protected function run(DeployContext $ctx): string
    {
        $format = ArtifactConfig::format();
        $extractor = ExtractorFactory::make($format);

        if (! $extractor::isSupported()) {
            throw DeployException::unprocessable(
                "PHP extension {$extractor::requiredExtension()} required for artifact.format '{$format}' is not available."
            );
        }

        $path = ArtifactConfig::path($ctx);
        if (! is_file($path)) {
            throw DeployException::unprocessable("Artifact not found: {$path}");
        }

        // Always verify the archive is openable / its index is readable.
        $extractor->assertReadable($path);

        $notes = ["format={$format}", 'archive readable'];
        $notes[] = $this->verifyChecksum($ctx, $path);

        return implode('; ', $notes);
    }

    private function verifyChecksum(DeployContext $ctx, string $path): string
    {
        $expected = $this->expectedChecksum($ctx);
        $require = (bool) config('artifact-deployer.artifact.require_checksum', false);

        if ($expected === null) {
            if ($require) {
                throw DeployException::unprocessable('Checksum required but none provided (no .sha256 file or body hash).');
            }

            return 'checksum not provided (skipped)';
        }

        $actual = hash_file('sha256', $path);
        if ($actual === false || ! hash_equals(strtolower($expected), strtolower($actual))) {
            throw DeployException::unprocessable('SHA-256 checksum mismatch.');
        }

        return 'sha256 match';
    }

    private function expectedChecksum(DeployContext $ctx): ?string
    {
        // 1. Hash passed in the request body / CLI option.
        if (! empty($ctx->sha256)) {
            return $this->firstHash($ctx->sha256);
        }

        // 2. A .sha256 file sitting next to the artifact.
        $checksumPath = ArtifactConfig::checksumPath();
        if ($checksumPath !== null && is_file($checksumPath)) {
            $contents = (string) file_get_contents($checksumPath);

            return $this->firstHash($contents);
        }

        return null;
    }

    /** Extracts the bare hex hash from a value like "abcd...  build_artifact.zip". */
    private function firstHash(string $value): ?string
    {
        if (preg_match('/[a-fA-F0-9]{64}/', $value, $m)) {
            return $m[0];
        }

        return null;
    }
}

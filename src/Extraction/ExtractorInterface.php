<?php

namespace Rekuest\ArtifactDeployer\Extraction;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;

interface ExtractorInterface
{
    /**
     * Extract $archivePath into $destination.
     *
     * Every entry MUST be validated (anti path-traversal + protected paths)
     * BEFORE any file is written. On the first unsafe entry the whole archive
     * is rejected with no partial extraction (fail-closed).
     *
     * $onValidated, when given, is invoked exactly once after the whole index
     * has been validated and immediately BEFORE the first byte is written. It
     * marks the point of no return (mutation about to begin). It is never
     * called when the archive is rejected during validation.
     *
     * @param  array<int, string>  $protectedPaths
     * @return int number of files extracted
     *
     * @throws DeployException on unsafe entry or unreadable archive
     */
    public function extract(string $archivePath, string $destination, array $protectedPaths = [], ?callable $onValidated = null): int;

    /**
     * Assert the archive exists and is openable / its index is readable,
     * without extracting anything. Throws on a corrupt or truncated archive.
     */
    public function assertReadable(string $archivePath): void;

    /** True if the PHP extension required by this format is available. */
    public static function isSupported(): bool;

    /** Human-readable name of the PHP extension this extractor needs. */
    public static function requiredExtension(): string;
}

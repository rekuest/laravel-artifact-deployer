<?php

namespace Rekuest\ArtifactDeployer\Extraction;

use PharData;
use RecursiveIteratorIterator;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Support\SafePath;
use Throwable;

final class TarGzExtractor implements ExtractorInterface
{
    public static function isSupported(): bool
    {
        return class_exists(PharData::class);
    }

    public static function requiredExtension(): string
    {
        return 'ext-phar';
    }

    public function assertReadable(string $archivePath): void
    {
        try {
            $phar = new PharData($archivePath);
            // Touch the index so a truncated archive surfaces now.
            $phar->count();
        } catch (Throwable $e) {
            throw DeployException::unprocessable('Cannot open tar.gz archive: '.$e->getMessage());
        }
    }

    public function extract(string $archivePath, string $destination, array $protectedPaths = [], ?callable $onValidated = null): int
    {
        try {
            $phar = new PharData($archivePath);
        } catch (Throwable $e) {
            throw DeployException::unprocessable('Cannot open tar.gz archive: '.$e->getMessage());
        }

        // Phase 1: validate EVERY entry before writing anything (fail-closed).
        $files = [];
        $iterator = new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', $iterator->getSubPathName());

            if ($file->isLink()) {
                throw DeployException::unprocessable("Symlink entry not allowed: {$relative}");
            }

            if (SafePath::isProtected($relative, $protectedPaths)) {
                throw DeployException::unprocessable("Artifact attempts to overwrite a protected path: {$relative}");
            }

            SafePath::resolveWithin($destination, $relative);

            if ($file->isFile()) {
                $files[] = $relative;
            }
        }

        // Point of no return: validation passed, writing is about to begin.
        if ($onValidated !== null) {
            $onValidated();
        }

        // Phase 2: all entries validated -> extract (overwrite enabled).
        try {
            $phar->extractTo($destination, null, true);
        } catch (Throwable $e) {
            throw DeployException::server('tar.gz extraction failed: '.$e->getMessage(), $e);
        }

        return count($files);
    }
}

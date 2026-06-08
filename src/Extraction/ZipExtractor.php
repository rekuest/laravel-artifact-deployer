<?php

namespace Rekuest\ArtifactDeployer\Extraction;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Support\SafePath;
use ZipArchive;

final class ZipExtractor implements ExtractorInterface
{
    public static function isSupported(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public static function requiredExtension(): string
    {
        return 'ext-zip';
    }

    public function assertReadable(string $archivePath): void
    {
        $zip = new ZipArchive;
        $res = $zip->open($archivePath, ZipArchive::CHECKCONS);
        if ($res !== true) {
            throw DeployException::unprocessable("Cannot open zip archive (error code {$res}).");
        }
        $zip->close();
    }

    public function extract(string $archivePath, string $destination, array $protectedPaths = [], ?callable $onValidated = null): int
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw DeployException::unprocessable('Cannot open zip archive.');
        }

        try {
            // Phase 1: validate EVERY entry before writing anything (fail-closed).
            // Nothing is written until the whole index is proven safe.
            $fileCount = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw DeployException::unprocessable("Unreadable zip entry at index {$i}.");
                }
                $name = $stat['name'];

                $this->guardSymlink($zip, $i, $name);

                if (SafePath::isProtected($name, $protectedPaths)) {
                    throw DeployException::unprocessable("Artifact attempts to overwrite a protected path: {$name}");
                }

                SafePath::resolveWithin($destination, $name);

                if (! str_ends_with($name, '/')) {
                    $fileCount++;
                }
            }

            // Point of no return: validation passed, writing is about to begin.
            if ($onValidated !== null) {
                $onValidated();
            }

            // Phase 2: every entry is proven safe, so the native (C-level)
            // extractTo() can be used without re-streaming each file in PHP.
            if (! $zip->extractTo($destination)) {
                throw DeployException::server('Zip extraction failed.');
            }

            return $fileCount;
        } finally {
            $zip->close();
        }
    }

    /**
     * Reject entries stored as symbolic links (unix mode S_IFLNK in the high
     * bits of the external attributes).
     */
    private function guardSymlink(ZipArchive $zip, int $index, string $name): void
    {
        $opsys = 0;
        $attr = 0;
        if ($zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            if ($opsys === ZipArchive::OPSYS_UNIX) {
                $unixMode = $attr >> 16;
                // S_IFLNK = 0xA000
                if (($unixMode & 0xF000) === 0xA000) {
                    throw DeployException::unprocessable("Symlink entry not allowed: {$name}");
                }
            }
        }
    }
}

<?php

namespace Rekuest\ArtifactDeployer\Packing;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use ZipArchive;

final class ZipPacker implements PackerInterface
{
    public static function isSupported(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public static function requiredExtension(): string
    {
        return 'ext-zip';
    }

    public function pack(string $destPath, array $files): int
    {
        $zip = new ZipArchive;
        if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw DeployException::server("Cannot create zip archive: {$destPath}");
        }

        foreach ($files as $relative => $absolute) {
            if (! $zip->addFile($absolute, $relative)) {
                $zip->close();
                throw DeployException::server("Cannot add file to archive: {$relative}");
            }
        }

        if (! $zip->close()) {
            throw DeployException::server("Failed to finalise zip archive: {$destPath}");
        }

        return count($files);
    }
}

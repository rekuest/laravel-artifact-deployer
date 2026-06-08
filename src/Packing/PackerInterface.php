<?php

namespace Rekuest\ArtifactDeployer\Packing;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;

interface PackerInterface
{
    /**
     * Write the given files into an artifact at $destPath.
     *
     * @param  array<string, string>  $files  map of [relativePath => absolutePath]
     * @return int number of files packed
     *
     * @throws DeployException
     */
    public function pack(string $destPath, array $files): int;

    /** True if the PHP extension required by this format is available. */
    public static function isSupported(): bool;

    /** Human-readable name of the PHP extension this packer needs. */
    public static function requiredExtension(): string;
}

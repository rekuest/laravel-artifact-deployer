<?php

namespace Rekuest\ArtifactDeployer\Packing;

use InvalidArgumentException;

final class PackerFactory
{
    public static function make(string $format): PackerInterface
    {
        return match ($format) {
            'zip' => new ZipPacker,
            'targz' => new TarGzPacker,
            default => throw new InvalidArgumentException("Unsupported artifact format: {$format}"),
        };
    }
}

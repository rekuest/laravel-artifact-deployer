<?php

namespace Rekuest\ArtifactDeployer\Extraction;

use InvalidArgumentException;

final class ExtractorFactory
{
    public static function make(string $format): ExtractorInterface
    {
        return match ($format) {
            'zip' => new ZipExtractor,
            'targz' => new TarGzExtractor,
            default => throw new InvalidArgumentException("Unsupported artifact format: {$format}"),
        };
    }
}

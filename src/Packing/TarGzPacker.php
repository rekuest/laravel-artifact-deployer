<?php

namespace Rekuest\ArtifactDeployer\Packing;

use ArrayIterator;
use Phar;
use PharData;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Throwable;

final class TarGzPacker implements PackerInterface
{
    public static function isSupported(): bool
    {
        return class_exists(PharData::class) && ! ini_get('phar.readonly');
    }

    public static function requiredExtension(): string
    {
        return 'ext-phar (with phar.readonly=0)';
    }

    public function pack(string $destPath, array $files): int
    {
        // PharData builds an uncompressed .tar first, then compresses to .gz.
        $tarPath = $this->tarPathFor($destPath);

        foreach ([$tarPath, $tarPath.'.gz', $destPath] as $stale) {
            if (is_file($stale)) {
                @unlink($stale);
            }
        }

        try {
            $phar = new PharData($tarPath);
            // ArrayIterator keys become the in-archive paths, values the sources.
            $phar->buildFromIterator(new ArrayIterator($files));
            $phar->compress(Phar::GZ);
            unset($phar);
        } catch (Throwable $e) {
            throw DeployException::server('tar.gz packing failed: '.$e->getMessage(), $e);
        }

        @unlink($tarPath);

        if ($tarPath.'.gz' !== $destPath && is_file($tarPath.'.gz')) {
            rename($tarPath.'.gz', $destPath);
        }

        return count($files);
    }

    private function tarPathFor(string $destPath): string
    {
        $dir = dirname($destPath);
        $base = preg_replace('/\.(tar\.gz|tgz|gz)$/i', '', basename($destPath));

        return $dir.'/'.$base.'.tar';
    }
}

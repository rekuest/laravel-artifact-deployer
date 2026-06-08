<?php

namespace Rekuest\ArtifactDeployer\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use SplFileInfo;

/**
 * Walks a source directory and returns the files to include in an artifact,
 * honouring exclude patterns (same convention as SafePath::isProtected) and
 * pruning excluded directories from the descent. Symlinks are skipped.
 */
final class FileCollector
{
    /**
     * @param  array<int, string>  $exclude
     * @return array<string, string> map of [relativePath => absolutePath]
     */
    public static function collect(string $sourceDir, array $exclude = []): array
    {
        $source = realpath($sourceDir);
        if ($source === false || ! is_dir($source)) {
            throw DeployException::server("Pack source directory not found: {$sourceDir}");
        }
        $source = rtrim(str_replace('\\', '/', $source), '/');
        $prefixLen = strlen($source) + 1;

        $directory = new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS);

        $filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $current) use ($source, $prefixLen, $exclude) {
            if ($current->isLink()) {
                return false;
            }

            $relative = self::relative($current->getPathname(), $source, $prefixLen);

            // Returning false on a directory prunes its whole subtree.
            return ! SafePath::isProtected($relative, $exclude);
        });

        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY);

        $files = [];
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = self::relative($file->getPathname(), $source, $prefixLen);
            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }

    private static function relative(string $pathname, string $source, int $prefixLen): string
    {
        $normalised = str_replace('\\', '/', $pathname);

        return str_starts_with($normalised, $source.'/')
            ? substr($normalised, $prefixLen)
            : ltrim($normalised, '/');
    }
}

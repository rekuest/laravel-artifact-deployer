<?php

namespace Rekuest\ArtifactDeployer\Support;

use Rekuest\ArtifactDeployer\Exceptions\DeployException;

final class SafePath
{
    /**
     * Resolve an archive entry inside $base and verify it does NOT escape the
     * destination. Rejects absolute paths, ".." components and null bytes.
     *
     * The path is collapsed lexically (without touching the filesystem) so it
     * works even for entries that do not exist on disk yet.
     *
     * @throws DeployException on any unsafe entry (fail-closed).
     */
    public static function resolveWithin(string $base, string $entryName): string
    {
        if (str_contains($entryName, "\0")) {
            throw DeployException::unprocessable('Invalid archive entry (null byte).');
        }

        // Normalise separators and reject absolute paths (unix + windows drive).
        $entry = str_replace('\\', '/', $entryName);
        if (str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:/#', $entry)) {
            throw DeployException::unprocessable("Absolute path not allowed: {$entryName}");
        }

        $baseReal = rtrim(str_replace('\\', '/', realpath($base) ?: $base), '/');
        $target = $baseReal.'/'.$entry;

        // Collapse the segments lexically.
        $parts = [];
        foreach (explode('/', $target) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (empty($parts)) {
                    throw DeployException::unprocessable("Path traversal detected: {$entryName}");
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $seg;
        }
        $resolved = '/'.implode('/', $parts);

        if ($resolved !== $baseReal && ! str_starts_with($resolved, $baseReal.'/')) {
            throw DeployException::unprocessable("Entry outside the destination: {$entryName}");
        }

        return $resolved;
    }

    /**
     * Whether the relative entry path hits one of the protected paths/globs.
     *
     * Rules per protected entry:
     *   - trailing slash  -> directory prefix match  ("storage/" blocks "storage/x")
     *   - contains * or ? -> fnmatch glob
     *   - otherwise       -> exact file match or its directory prefix
     *
     * @param  array<int, string>  $protected
     */
    public static function isProtected(string $entryName, array $protected): bool
    {
        $entry = ltrim(str_replace('\\', '/', $entryName), '/');

        foreach ($protected as $rule) {
            $rule = str_replace('\\', '/', (string) $rule);
            if ($rule === '') {
                continue;
            }

            if (str_ends_with($rule, '/')) {
                $prefix = rtrim($rule, '/');
                if ($entry === $prefix || str_starts_with($entry, $prefix.'/')) {
                    return true;
                }

                continue;
            }

            if (str_contains($rule, '*') || str_contains($rule, '?')) {
                if (fnmatch($rule, $entry)) {
                    return true;
                }

                continue;
            }

            if ($entry === $rule || str_starts_with($entry, $rule.'/')) {
                return true;
            }
        }

        return false;
    }
}

<?php

use Rekuest\ArtifactDeployer\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Create a unique temporary directory for a test and return its path.
 */
function radTempDir(string $prefix = 'rad'): string
{
    $dir = sys_get_temp_dir().'/'.$prefix.'_'.bin2hex(random_bytes(6));
    mkdir($dir, 0o755, true);

    return $dir;
}

/**
 * Build a zip archive at $path from [relativeName => contents].
 *
 * @param  array<string, string>  $files
 */
function radMakeZip(string $path, array $files): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return $path;
}

/**
 * Build a zip containing a single symlink entry (unix S_IFLNK mode).
 */
function radMakeSymlinkZip(string $path, string $name = 'link', string $target = '/etc/passwd'): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString($name, $target);
    // S_IFLNK (0xA000) | 0777, shifted into the high 16 bits of external attrs.
    $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0xA1FF) << 16);
    $zip->close();

    return $path;
}

/**
 * Build a tar.gz archive at $path from [relativeName => contents].
 *
 * @param  array<string, string>  $files
 */
function radMakeTarGz(string $path, array $files): string
{
    // PharData needs the .tar name first, then compress to .tar.gz.
    $tarPath = preg_replace('/\.tar\.gz$|\.tgz$/', '.tar', $path);
    @unlink($tarPath);
    @unlink($path);

    $phar = new PharData($tarPath);
    foreach ($files as $name => $contents) {
        $phar->addFromString($name, $contents);
    }
    $phar->compress(Phar::GZ);
    unset($phar);

    // compress() writes <tar>.gz; rename to the requested path if needed.
    if ($tarPath.'.gz' !== $path) {
        rename($tarPath.'.gz', $path);
    }
    @unlink($tarPath);

    return $path;
}

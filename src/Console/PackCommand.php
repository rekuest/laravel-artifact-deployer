<?php

namespace Rekuest\ArtifactDeployer\Console;

use Illuminate\Console\Command;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Packing\PackerFactory;
use Rekuest\ArtifactDeployer\Support\FileCollector;

/**
 * Builds the deploy artifact on the CI/build machine — the symmetric
 * counterpart of rad:run. Replaces a raw `zip -r build_artifact.zip .`:
 * honours artifact.format, names the archive from artifact.path, applies the
 * configured exclusions (and always excludes the archive itself + its .sha256),
 * and optionally writes a .sha256 sidecar.
 */
class PackCommand extends Command
{
    protected $signature = 'rad:pack
        {--output= : Output archive path (overrides artifact.path)}
        {--format= : Artifact format: zip|targz (overrides config)}
        {--source= : Directory to pack (overrides config, default: project root)}
        {--no-checksum : Do not write the .sha256 sidecar}';

    protected $description = 'Build the deploy artifact (zip/tar.gz) from the project, ready to ship to the server.';

    public function handle(): int
    {
        $format = (string) ($this->option('format') ?: config('artifact-deployer.artifact.format', 'zip'));
        $output = (string) ($this->option('output') ?: config('artifact-deployer.artifact.path', base_path('build_artifact.zip')));
        $source = (string) ($this->option('source') ?: config('artifact-deployer.artifact.pack.source', base_path()));

        $packer = PackerFactory::make($format);
        if (! $packer::isSupported()) {
            $this->error("PHP extension {$packer::requiredExtension()} is required for format '{$format}'.");

            return self::FAILURE;
        }

        $exclude = $this->excludes($output);

        try {
            $files = FileCollector::collect($source, $exclude);
            if ($files === []) {
                $this->error("No files to pack in {$source} (everything excluded?).");

                return self::FAILURE;
            }

            $this->ensureDir(dirname($output));
            $count = $packer->pack($output, $files);
        } catch (DeployException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $size = $this->humanSize((int) (filesize($output) ?: 0));
        $this->info("Packed {$count} files into {$output} ({$size}).");

        if (! $this->option('no-checksum') && config('artifact-deployer.artifact.pack.checksum', true)) {
            $sha = hash_file('sha256', $output);
            $sidecar = $output.'.sha256';
            file_put_contents($sidecar, $sha.'  '.basename($output)."\n");
            $this->line("SHA-256: {$sha}");
            $this->line("Sidecar: {$sidecar}");
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function excludes(string $output): array
    {
        $exclude = (array) config('artifact-deployer.artifact.pack.exclude', []);

        // Never include the archive being written, nor its sidecar/renamed variants.
        $base = basename($output);
        $exclude[] = $base;
        $exclude[] = $base.'.*';

        return array_values(array_unique($exclude));
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw DeployException::server("Cannot create output directory: {$dir}");
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }
}

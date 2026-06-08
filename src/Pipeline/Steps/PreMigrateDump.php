<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Symfony\Component\Process\Process;

/**
 * Optional, MANUAL disaster-recovery dump taken before `migrate`. NOT a rollback
 * mechanism (see package docs §2.13). Disabled by default. Currently supports
 * the mysql/mariadb drivers via mysqldump; other drivers fail explicitly when
 * the step is enabled.
 */
class PreMigrateDump extends AbstractStep
{
    public function name(): string
    {
        return 'pre_migrate_dump';
    }

    protected function run(DeployContext $ctx): string
    {
        if (! config('artifact-deployer.pre_migrate_dump.enabled', false)) {
            return 'disabled (skipped)';
        }

        if ($ctx->dryRun) {
            return 'dry-run: skipped';
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw DeployException::server("pre_migrate_dump does not support the '{$driver}' driver.");
        }

        $disk = Storage::disk(config('artifact-deployer.pre_migrate_dump.disk', 'local'));
        $dir = trim((string) config('artifact-deployer.pre_migrate_dump.path', 'artifact-deployer/db-dumps'), '/');
        $disk->makeDirectory($dir);

        $filename = $dir.'/'.$ctx->deployId.'.sql.gz';
        $absolute = $disk->path($filename);

        $cfg = config("database.connections.{$connection}");
        $this->dump($cfg, $absolute);

        $this->pruneOldDumps($disk, $dir);

        return "dump saved: {$filename}";
    }

    /** @param  array<string, mixed>  $cfg */
    private function dump(array $cfg, string $absolute): void
    {
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s | gzip > %s',
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($cfg['port'] ?? 3306)),
            escapeshellarg((string) ($cfg['username'] ?? 'root')),
            escapeshellarg((string) ($cfg['database'] ?? '')),
            escapeshellarg($absolute),
        );

        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => (string) ($cfg['password'] ?? ''),
        ]);
        $process->setTimeout((float) config('artifact-deployer.timeout', 600));
        $process->run();

        if (! $process->isSuccessful()) {
            throw DeployException::server('Database dump failed: '.trim($process->getErrorOutput()));
        }
    }

    private function pruneOldDumps(Filesystem $disk, string $dir): void
    {
        $keep = (int) config('artifact-deployer.pre_migrate_dump.keep', 3);
        $files = collect($disk->files($dir))->sort()->values();

        if ($files->count() <= $keep) {
            return;
        }

        $files->slice(0, $files->count() - $keep)
            ->each(fn (string $file) => $disk->delete($file));
    }
}

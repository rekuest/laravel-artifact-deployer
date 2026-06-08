<?php

namespace Rekuest\ArtifactDeployer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StatusCommand extends Command
{
    protected $signature = 'rad:status {--json : Output the raw state JSON}';

    protected $description = 'Show the outcome of the last artifact deploy.';

    public function handle(): int
    {
        $path = storage_path('app/'.trim((string) config('artifact-deployer.state_path', 'artifact-deployer'), '/').'/last.json');

        if (! File::exists($path)) {
            $this->warn('No deploy recorded yet.');

            return self::SUCCESS;
        }

        $raw = (string) File::get($path);

        if ($this->option('json')) {
            $this->line($raw);

            return self::SUCCESS;
        }

        $state = json_decode($raw, true);
        if (! is_array($state)) {
            $this->error('Corrupt state file.');

            return self::FAILURE;
        }

        $this->line('Deploy ID : '.($state['deploy_id'] ?? 'n/a'));
        $this->line('Status    : '.($state['status'] ?? 'n/a'));
        $this->line('Duration  : '.($state['duration_ms'] ?? 'n/a').'ms');
        if (! empty($state['error'])) {
            $this->line('Error     : '.$state['error']);
        }

        $this->newLine();
        foreach ((array) ($state['steps'] ?? []) as $step) {
            $this->line(sprintf('  [%s] %s (%dms)', $step['status'] ?? '?', $step['name'] ?? '?', $step['duration_ms'] ?? 0));
        }

        return ($state['status'] ?? null) === 'success' ? self::SUCCESS : self::FAILURE;
    }
}

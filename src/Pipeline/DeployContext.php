<?php

namespace Rekuest\ArtifactDeployer\Pipeline;

use Throwable;

/**
 * Carries the state and results of a single deploy run. Steps read their inputs
 * (artifact path, sha256, flags) from here and record their output back into it.
 */
class DeployContext
{
    /** @var array<int, array{name: string, status: string, output: string, duration_ms: int}> */
    public array $steps = [];

    public string $status = 'running';

    public ?string $error = null;

    public ?Throwable $exception = null;

    public bool $maintenanceActive = false;

    /**
     * True once the deploy has started mutating the app (writing extracted
     * files or running migrations). While false, a failure left the app
     * untouched, so it is always safe to bring the site back up.
     */
    public bool $mutationStarted = false;

    public float $startedAt;

    public ?int $durationMs = null;

    public function __construct(
        public readonly string $deployId,
        public readonly ?string $artifactPath = null,
        public readonly ?string $sha256 = null,
        public readonly bool $dryRun = false,
        public readonly bool $maintenance = true,
    ) {
        $this->startedAt = microtime(true);
    }

    public static function makeId(): string
    {
        return now()->format('Y-m-d\TH-i-s').'_'.substr(md5(uniqid('', true)), 0, 6);
    }

    public function recordStep(string $name, string $status, string $output, float $startedAt): void
    {
        $this->steps[] = [
            'name' => $name,
            'status' => $status,
            'output' => trim($output),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function fail(Throwable $e): void
    {
        $this->status = 'failed';
        $this->exception = $e;
        $this->error = $e->getMessage();
    }

    public function succeed(): void
    {
        $this->status = 'success';
    }

    public function finish(): void
    {
        $this->durationMs ??= (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'deploy_id' => $this->deployId,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
            'steps' => $this->steps,
        ];
    }

    /**
     * Public response payload: never includes the exception/stack trace.
     *
     * @return array<string, mixed>
     */
    public function toResponse(): array
    {
        $payload = [
            'deploy_id' => $this->deployId,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'steps' => $this->steps,
        ];

        if ($this->status === 'failed') {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}

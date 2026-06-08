<?php

namespace Rekuest\ArtifactDeployer\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Atomic, single-flight lock around a deploy run. Serialises concurrent deploys
 * so two parallel triggers cannot corrupt migrations or race on files.
 */
class DeployLock
{
    protected ?Lock $lock = null;

    public function __construct(
        protected string $name = 'rad:run',
        protected int $ttl = 600,
    ) {}

    /** Try to acquire the lock. Returns false if a deploy is already running. */
    public function acquire(): bool
    {
        $this->lock = Cache::lock($this->name, $this->ttl);

        return $this->lock->get();
    }

    public function release(): void
    {
        $this->lock?->forceRelease();
        $this->lock = null;
    }
}

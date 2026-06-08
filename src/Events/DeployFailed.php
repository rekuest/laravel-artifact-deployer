<?php

namespace Rekuest\ArtifactDeployer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Throwable;

class DeployFailed
{
    use Dispatchable;

    public function __construct(
        public readonly DeployContext $context,
        public readonly Throwable $exception,
    ) {}
}

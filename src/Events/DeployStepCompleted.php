<?php

namespace Rekuest\ArtifactDeployer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\StepInterface;

class DeployStepCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly DeployContext $context,
        public readonly StepInterface $step,
    ) {}
}

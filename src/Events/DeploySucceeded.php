<?php

namespace Rekuest\ArtifactDeployer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;

class DeploySucceeded
{
    use Dispatchable;

    public function __construct(public readonly DeployContext $context) {}
}

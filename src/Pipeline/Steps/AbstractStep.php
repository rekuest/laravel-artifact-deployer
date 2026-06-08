<?php

namespace Rekuest\ArtifactDeployer\Pipeline\Steps;

use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\StepInterface;

abstract class AbstractStep implements StepInterface
{
    public function handle(DeployContext $ctx): void
    {
        $start = microtime(true);
        $output = $this->run($ctx);
        $ctx->recordStep($this->name(), 'ok', $output, $start);
    }

    /** Execute the step and return a short human-readable output. Throw to fail. */
    abstract protected function run(DeployContext $ctx): string;
}

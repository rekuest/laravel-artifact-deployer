<?php

namespace Rekuest\ArtifactDeployer\Pipeline;

interface StepInterface
{
    /** Machine name of the step (used in logs, events and the JSON response). */
    public function name(): string;

    /**
     * Execute the step against the shared context. Record output/duration into
     * the context and throw on failure.
     */
    public function handle(DeployContext $ctx): void;
}

<?php

namespace Rekuest\ArtifactDeployer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\DeployPipeline;

class RunController
{
    public function run(Request $request, DeployPipeline $pipeline): JsonResponse
    {
        $ctx = new DeployContext(
            deployId: DeployContext::makeId(),
            artifactPath: $request->input('artifact'),
            sha256: $request->input('sha256'),
        );

        try {
            $pipeline->run($ctx);
        } catch (DeployException $e) {
            // Lock conflict (409) and other pre-run domain errors.
            return response()->json([
                'deploy_id' => $ctx->deployId,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], $e->statusCode);
        }

        return response()->json($ctx->toResponse(), $this->statusFor($ctx));
    }

    private function statusFor(DeployContext $ctx): int
    {
        if ($ctx->status === 'success') {
            return 200;
        }

        $e = $ctx->exception;

        return $e instanceof DeployException ? $e->statusCode : 500;
    }
}

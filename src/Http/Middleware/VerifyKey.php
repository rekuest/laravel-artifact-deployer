<?php

namespace Rekuest\ArtifactDeployer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('artifact-deployer.enabled', true)) {
            abort(503, 'Artifact deployer disabled.');
        }

        $allowed = (array) config('artifact-deployer.allowed_environments', []);
        if (! empty($allowed) && ! in_array(app()->environment(), $allowed, true)) {
            abort(403, 'Environment not allowed.');
        }

        $key = config('artifact-deployer.auth.key');
        if (empty($key)) {
            // Fail-closed: never auto-generate a silent random key.
            abort(500, 'Deploy key not configured (RAD_KEY).');
        }

        $header = config('artifact-deployer.auth.header', 'laravel-artifact-deployer-key');
        $token = $request->header($header);

        if (empty($token) && config('artifact-deployer.auth.allow_query_string', false)) {
            $token = $request->query(config('artifact-deployer.auth.query_param', 'key'));
        }

        if (! is_string($token) || ! hash_equals((string) $key, $token)) {
            Log::channel(config('artifact-deployer.log_channel', 'stack'))->warning(
                'Artifact deployer: rejected unauthenticated deploy attempt.',
                ['ip' => $request->ip(), 'at' => now()->toIso8601String()],
            );
            abort(401);
        }

        return $next($request);
    }
}

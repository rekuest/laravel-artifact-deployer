<?php

use Illuminate\Support\Facades\Config;

function radPost(array $headers = [], array $query = [])
{
    $url = '/artifact-deployer/run';
    if ($query !== []) {
        $url .= '?'.http_build_query($query);
    }

    return test()->postJson($url, [], $headers);
}

it('returns 500 when the key is not configured (fail-closed)', function () {
    Config::set('artifact-deployer.auth.key', null);

    radPost(['laravel-artifact-deployer-key' => 'whatever'])
        ->assertStatus(500);
});

it('returns 401 with a wrong key', function () {
    radPost(['laravel-artifact-deployer-key' => 'wrong-key'])
        ->assertStatus(401);
});

it('returns 401 with no key', function () {
    radPost()->assertStatus(401);
});

it('passes the middleware with the correct key', function () {
    // Missing artifact -> 422 means we got PAST the auth middleware.
    Config::set('artifact-deployer.artifact.path', '/nonexistent/artifact.zip');

    radPost(['laravel-artifact-deployer-key' => 'test-secret-key'])
        ->assertStatus(422);
});

it('returns 403 for a disallowed environment', function () {
    Config::set('artifact-deployer.allowed_environments', ['production']);

    radPost(['laravel-artifact-deployer-key' => 'test-secret-key'])
        ->assertStatus(403);
});

it('returns 503 when disabled', function () {
    Config::set('artifact-deployer.enabled', false);

    radPost(['laravel-artifact-deployer-key' => 'test-secret-key'])
        ->assertStatus(503);
});

it('rejects the query-string key by default', function () {
    radPost([], ['key' => 'test-secret-key'])->assertStatus(401);
});

it('accepts the query-string key only when enabled', function () {
    Config::set('artifact-deployer.auth.allow_query_string', true);
    Config::set('artifact-deployer.artifact.path', '/nonexistent/artifact.zip');

    radPost([], ['key' => 'test-secret-key'])->assertStatus(422);
});

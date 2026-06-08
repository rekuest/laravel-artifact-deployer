<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->work = radTempDir('endpoint');
    $this->extractTo = $this->work.'/app';
    mkdir($this->extractTo, 0o755, true);
    $this->artifact = $this->work.'/build_artifact.zip';

    Config::set('artifact-deployer.artifact.path', $this->artifact);
    Config::set('artifact-deployer.artifact.extract_to', $this->extractTo);
    Config::set('artifact-deployer.artifact.after', 'keep');
    Config::set('artifact-deployer.commands', [
        'package_discover' => false,
        'migrate' => false,
        'custom' => [],
        'optimize_clear' => false,
    ]);
    Config::set('artifact-deployer.restart.queue', false);
    Config::set('artifact-deployer.maintenance.enabled', false);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->work));
});

function radDeploy()
{
    return test()->postJson('/artifact-deployer/run', [], [
        'laravel-artifact-deployer-key' => 'test-secret-key',
    ]);
}

it('runs a full deploy successfully', function () {
    radMakeZip($this->artifact, [
        'routes/web.php' => '<?php // routes',
        'public/index.php' => '<?php echo 1;',
    ]);

    $response = radDeploy();

    $response->assertStatus(200)
        ->assertJson(['status' => 'success'])
        ->assertJsonStructure(['deploy_id', 'status', 'duration_ms', 'steps']);

    expect(is_file($this->extractTo.'/routes/web.php'))->toBeTrue()
        ->and(is_file($this->extractTo.'/public/index.php'))->toBeTrue();
});

it('returns 422 when the artifact is missing', function () {
    radDeploy()->assertStatus(422);
});

it('returns 409 when a deploy is already running', function () {
    radMakeZip($this->artifact, ['x.php' => '<?php']);

    // Hold the lock to simulate a concurrent deploy.
    Cache::lock('rad:run', 600)->get();

    radDeploy()->assertStatus(409);
});

it('verifies a provided sha256 and rejects a mismatch', function () {
    radMakeZip($this->artifact, ['x.php' => '<?php']);

    test()->postJson('/artifact-deployer/run', ['sha256' => str_repeat('0', 64)], [
        'laravel-artifact-deployer-key' => 'test-secret-key',
    ])->assertStatus(422);
});

it('accepts a correct provided sha256', function () {
    radMakeZip($this->artifact, ['x.php' => '<?php']);
    $sha = hash_file('sha256', $this->artifact);

    test()->postJson('/artifact-deployer/run', ['sha256' => $sha], [
        'laravel-artifact-deployer-key' => 'test-secret-key',
    ])->assertStatus(200);
});

it('rejects an artifact with a path-traversal entry without writing', function () {
    radMakeZip($this->artifact, [
        'safe.php' => '<?php',
        '../escaped.php' => 'evil',
    ]);

    radDeploy()->assertStatus(422);

    expect(is_file($this->extractTo.'/safe.php'))->toBeFalse()
        ->and(is_file(dirname($this->extractTo).'/escaped.php'))->toBeFalse();
});

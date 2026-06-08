<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\DeployPipeline;

afterEach(function () {
    // Make sure no test leaves the app down.
    Artisan::call('up');
});

it('excludes the deploy route from maintenance mode (no secret needed)', function () {
    $excluded = (new PreventRequestsDuringMaintenance($this->app))->getExcludedPaths();

    expect($excluded)->toContain('artifact-deployer/*');
});

it('brings the site back up when the deploy fails BEFORE any mutation', function () {
    Config::set('artifact-deployer.maintenance.enabled', true);
    Config::set('artifact-deployer.maintenance.keep_down_on_failure', true);
    Config::set('artifact-deployer.artifact.path', '/nonexistent/build_artifact.zip');

    $ctx = (new DeployPipeline)->run(new DeployContext(DeployContext::makeId()));

    expect($ctx->status)->toBe('failed')
        ->and($ctx->mutationStarted)->toBeFalse()
        ->and($this->app->maintenanceMode()->active())->toBeFalse();
});

it('keeps the site down when the deploy fails AFTER mutation has started', function () {
    $work = radTempDir('maint');
    $artifact = $work.'/build_artifact.zip';
    radMakeZip($artifact, ['public/index.php' => '<?php']);

    Config::set('artifact-deployer.maintenance.enabled', true);
    Config::set('artifact-deployer.maintenance.keep_down_on_failure', true);
    Config::set('artifact-deployer.artifact.path', $artifact);
    Config::set('artifact-deployer.artifact.extract_to', $work.'/dest');
    mkdir($work.'/dest', 0o755, true);
    Config::set('artifact-deployer.commands', [
        'package_discover' => false,
        'migrate' => false,
        'custom' => [['this-command-does-not-exist', []]],
        'optimize_clear' => false,
    ]);
    Config::set('artifact-deployer.restart.queue', false);

    $ctx = (new DeployPipeline)->run(new DeployContext(DeployContext::makeId()));

    expect($ctx->status)->toBe('failed')
        ->and($ctx->mutationStarted)->toBeTrue()
        ->and($this->app->maintenanceMode()->active())->toBeTrue();

    exec('rm -rf '.escapeshellarg($work));
});

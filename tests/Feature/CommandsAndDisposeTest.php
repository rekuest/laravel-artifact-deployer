<?php

use Illuminate\Support\Facades\Config;
use Rekuest\ArtifactDeployer\Pipeline\DeployContext;
use Rekuest\ArtifactDeployer\Pipeline\Steps\DisposeArtifact;
use Rekuest\ArtifactDeployer\Pipeline\Steps\RunArtisanCommands;

it('runs the artisan commands in the fixed order', function () {
    Config::set('artifact-deployer.commands', [
        'package_discover' => true,
        'migrate' => true,
        'custom' => [['about', []]],
        'optimize_clear' => true,
    ]);

    $ctx = new DeployContext('test-id');
    (new RunArtisanCommands)->handle($ctx);

    $names = array_map(fn ($s) => $s['name'], $ctx->steps);

    expect($names)->toBe([
        'run_artisan:package:discover',
        'run_artisan:migrate',
        'run_artisan:about',
        'run_artisan:optimize:clear',
    ]);
});

it('honours the migrate and optimize_clear toggles', function () {
    Config::set('artifact-deployer.commands', [
        'package_discover' => false,
        'migrate' => false,
        'custom' => [],
        'optimize_clear' => false,
    ]);

    $ctx = new DeployContext('test-id');
    (new RunArtisanCommands)->handle($ctx);

    expect($ctx->steps)->toBe([]);
});

it('renames the artifact after a successful deploy', function () {
    $dir = radTempDir('dispose');
    $artifact = $dir.'/build_artifact.zip';
    file_put_contents($artifact, 'data');
    Config::set('artifact-deployer.artifact.path', $artifact);
    Config::set('artifact-deployer.artifact.after', 'rename');
    Config::set('artifact-deployer.artifact.rename_suffix', '.deployed');

    (new DisposeArtifact)->handle(new DeployContext('test-id'));

    expect(is_file($artifact))->toBeFalse()
        ->and(is_file($artifact.'.deployed'))->toBeTrue();

    exec('rm -rf '.escapeshellarg($dir));
});

it('deletes the artifact when configured', function () {
    $dir = radTempDir('dispose');
    $artifact = $dir.'/build_artifact.zip';
    file_put_contents($artifact, 'data');
    Config::set('artifact-deployer.artifact.path', $artifact);
    Config::set('artifact-deployer.artifact.after', 'delete');

    (new DisposeArtifact)->handle(new DeployContext('test-id'));

    expect(is_file($artifact))->toBeFalse();

    exec('rm -rf '.escapeshellarg($dir));
});

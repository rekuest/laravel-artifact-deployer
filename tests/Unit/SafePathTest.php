<?php

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Support\SafePath;

beforeEach(function () {
    $this->base = radTempDir('safepath');
});

afterEach(function () {
    @rmdir($this->base);
});

it('resolves a normal entry inside the base', function () {
    $resolved = SafePath::resolveWithin($this->base, 'app/Http/Kernel.php');

    expect($resolved)->toStartWith(rtrim($this->base, '/').'/');
});

it('rejects a parent-traversal entry', function () {
    SafePath::resolveWithin($this->base, '../evil.php');
})->throws(DeployException::class);

it('rejects a nested parent-traversal entry', function () {
    SafePath::resolveWithin($this->base, 'a/b/../../../evil.php');
})->throws(DeployException::class);

it('rejects an absolute unix path', function () {
    SafePath::resolveWithin($this->base, '/etc/passwd');
})->throws(DeployException::class);

it('rejects a windows drive path', function () {
    SafePath::resolveWithin($this->base, 'C:/Windows/system32');
})->throws(DeployException::class);

it('rejects a null byte', function () {
    SafePath::resolveWithin($this->base, "evil\0.php");
})->throws(DeployException::class);

it('detects protected exact files', function () {
    expect(SafePath::isProtected('.env', ['.env', 'storage/']))->toBeTrue()
        ->and(SafePath::isProtected('.env.example', ['.env']))->toBeFalse();
});

it('detects protected directory prefixes', function () {
    expect(SafePath::isProtected('storage/logs/laravel.log', ['storage/']))->toBeTrue()
        ->and(SafePath::isProtected('storaged/x', ['storage/']))->toBeFalse();
});

it('detects protected globs', function () {
    expect(SafePath::isProtected('config/secret.key', ['*.key']))->toBeTrue();
});

<?php

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->work = radTempDir('packcmd');
    mkdir($this->work.'/app', 0o755, true);
    file_put_contents($this->work.'/app/Kernel.php', '<?php');
    file_put_contents($this->work.'/.env', 'SECRET');

    Config::set('artifact-deployer.artifact.format', 'zip');
    Config::set('artifact-deployer.artifact.path', $this->work.'/build_artifact.zip');
    Config::set('artifact-deployer.artifact.pack.source', $this->work);
    Config::set('artifact-deployer.artifact.pack.exclude', ['.env']);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->work));
});

it('builds the artifact and a sha256 sidecar, excluding configured paths and itself', function () {
    $this->artisan('rad:pack')->assertSuccessful();

    $archive = $this->work.'/build_artifact.zip';
    expect(is_file($archive))->toBeTrue()
        ->and(is_file($archive.'.sha256'))->toBeTrue();

    // The sidecar holds the real hash of the archive.
    $line = (string) file_get_contents($archive.'.sha256');
    expect($line)->toContain(hash_file('sha256', $archive))
        ->and($line)->toContain('build_artifact.zip');

    // The archive does not contain the excluded .env nor itself.
    $zip = new ZipArchive;
    $zip->open($archive);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->statIndex($i)['name'];
    }
    $zip->close();

    expect($names)->toContain('app/Kernel.php')
        ->and($names)->not->toContain('.env')
        ->and($names)->not->toContain('build_artifact.zip');
});

it('respects --no-checksum', function () {
    $this->artisan('rad:pack --no-checksum')->assertSuccessful();

    expect(is_file($this->work.'/build_artifact.zip'))->toBeTrue()
        ->and(is_file($this->work.'/build_artifact.zip.sha256'))->toBeFalse();
});

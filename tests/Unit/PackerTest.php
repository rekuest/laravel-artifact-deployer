<?php

use Rekuest\ArtifactDeployer\Extraction\TarGzExtractor;
use Rekuest\ArtifactDeployer\Extraction\ZipExtractor;
use Rekuest\ArtifactDeployer\Packing\TarGzPacker;
use Rekuest\ArtifactDeployer\Packing\ZipPacker;
use Rekuest\ArtifactDeployer\Support\FileCollector;

beforeEach(function () {
    $this->work = radTempDir('pack');
    $this->source = $this->work.'/src';
    mkdir($this->source.'/app', 0o755, true);
    mkdir($this->source.'/node_modules/pkg', 0o755, true);
    mkdir($this->source.'/.git', 0o755, true);

    file_put_contents($this->source.'/app/Kernel.php', '<?php // kernel');
    file_put_contents($this->source.'/composer.json', '{}');
    file_put_contents($this->source.'/.env', 'APP_KEY=secret');
    file_put_contents($this->source.'/node_modules/pkg/index.js', 'module.exports = 1;');
    file_put_contents($this->source.'/.git/config', '[core]');
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->work));
});

it('collects files while pruning excluded directories', function () {
    $files = FileCollector::collect($this->source, ['.git/', 'node_modules/', '.env']);

    expect($files)->toHaveKeys(['app/Kernel.php', 'composer.json'])
        ->and($files)->not->toHaveKey('.env')
        ->and(array_keys($files))->not->toContain('node_modules/pkg/index.js')
        ->and(array_keys($files))->not->toContain('.git/config');
});

it('packs a zip and round-trips through the extractor', function () {
    $archive = $this->work.'/build_artifact.zip';
    $dest = $this->work.'/dest';
    mkdir($dest, 0o755, true);

    $files = FileCollector::collect($this->source, ['.git/', 'node_modules/', '.env']);
    $packed = (new ZipPacker)->pack($archive, $files);

    expect($packed)->toBe(2)->and(is_file($archive))->toBeTrue();

    (new ZipExtractor)->extract($archive, $dest);

    expect(file_get_contents($dest.'/app/Kernel.php'))->toBe('<?php // kernel')
        ->and(is_file($dest.'/.env'))->toBeFalse()
        ->and(is_dir($dest.'/node_modules'))->toBeFalse();
});

it('packs a tar.gz and round-trips through the extractor', function () {
    $archive = $this->work.'/build_artifact.tar.gz';
    $dest = $this->work.'/dest-tar';
    mkdir($dest, 0o755, true);

    $files = FileCollector::collect($this->source, ['.git/', 'node_modules/', '.env']);
    $packed = (new TarGzPacker)->pack($archive, $files);

    expect($packed)->toBe(2)
        ->and(is_file($archive))->toBeTrue();

    (new TarGzExtractor)->extract($archive, $dest);

    expect(file_get_contents($dest.'/app/Kernel.php'))->toBe('<?php // kernel')
        ->and(is_file($dest.'/.env'))->toBeFalse();
})->skip(fn () => (bool) ini_get('phar.readonly'), 'phar.readonly is enabled');

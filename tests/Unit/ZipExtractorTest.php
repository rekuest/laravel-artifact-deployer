<?php

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Extraction\ZipExtractor;

beforeEach(function () {
    $this->work = radTempDir('zip');
    $this->dest = $this->work.'/dest';
    mkdir($this->dest, 0o755, true);
    $this->archive = $this->work.'/artifact.zip';
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->work));
});

it('extracts a valid archive', function () {
    radMakeZip($this->archive, [
        'public/index.php' => '<?php echo "ok";',
        'app/Models/User.php' => '<?php class User {}',
    ]);

    $count = (new ZipExtractor)->extract($this->archive, $this->dest);

    expect($count)->toBe(2)
        ->and(file_get_contents($this->dest.'/public/index.php'))->toBe('<?php echo "ok";')
        ->and(is_file($this->dest.'/app/Models/User.php'))->toBeTrue();
});

it('rejects a path-traversal entry and writes nothing', function () {
    radMakeZip($this->archive, [
        'ok.php' => 'x',
        '../escaped.php' => 'evil',
    ]);

    expect(fn () => (new ZipExtractor)->extract($this->archive, $this->dest))
        ->toThrow(DeployException::class);

    // Fail-closed: not even the safe file was written.
    expect(is_file($this->dest.'/ok.php'))->toBeFalse();
});

it('rejects an absolute-path entry', function () {
    radMakeZip($this->archive, ['/etc/cron.d/evil' => 'evil']);

    expect(fn () => (new ZipExtractor)->extract($this->archive, $this->dest))
        ->toThrow(DeployException::class);
});

it('rejects writing a protected path', function () {
    radMakeZip($this->archive, ['.env' => 'APP_KEY=stolen']);

    expect(fn () => (new ZipExtractor)->extract($this->archive, $this->dest, ['.env', 'storage/']))
        ->toThrow(DeployException::class);

    expect(is_file($this->dest.'/.env'))->toBeFalse();
});

it('rejects a symlink entry', function () {
    radMakeSymlinkZip($this->archive, 'link', '/etc/passwd');

    expect(fn () => (new ZipExtractor)->extract($this->archive, $this->dest))
        ->toThrow(DeployException::class);
});

it('throws on an unreadable archive', function () {
    file_put_contents($this->archive, 'not a zip at all');

    expect(fn () => (new ZipExtractor)->assertReadable($this->archive))
        ->toThrow(DeployException::class);
});

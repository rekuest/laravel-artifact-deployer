<?php

use Rekuest\ArtifactDeployer\Exceptions\DeployException;
use Rekuest\ArtifactDeployer\Extraction\TarGzExtractor;

beforeEach(function () {
    $this->work = radTempDir('targz');
    $this->dest = $this->work.'/dest';
    mkdir($this->dest, 0o755, true);
    $this->archive = $this->work.'/artifact.tar.gz';
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->work));
});

it('extracts a valid tar.gz archive', function () {
    radMakeTarGz($this->archive, [
        'public/index.php' => '<?php echo "ok";',
        'app/Models/User.php' => '<?php class User {}',
    ]);

    $count = (new TarGzExtractor)->extract($this->archive, $this->dest);

    expect($count)->toBe(2)
        ->and(file_get_contents($this->dest.'/public/index.php'))->toBe('<?php echo "ok";');
});

it('rejects writing a protected path', function () {
    radMakeTarGz($this->archive, ['.env' => 'APP_KEY=stolen']);

    expect(fn () => (new TarGzExtractor)->extract($this->archive, $this->dest, ['.env']))
        ->toThrow(DeployException::class);

    expect(is_file($this->dest.'/.env'))->toBeFalse();
});

it('throws on an unreadable tar.gz archive', function () {
    file_put_contents($this->archive, 'not a tarball');

    expect(fn () => (new TarGzExtractor)->assertReadable($this->archive))
        ->toThrow(DeployException::class);
});

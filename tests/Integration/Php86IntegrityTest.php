<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

pest()->extend(TestCase::class);

test('integrity checks preserve the manifest and detect lockfile tampering', function (): void {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/omxfc-integrity-'.bin2hex(random_bytes(12));
    mkdir($directory);
    $manifest = file_get_contents($root.'/composer.json');
    $lock = file_get_contents($root.'/composer.lock');
    file_put_contents($directory.'/composer.json', $manifest);
    file_put_contents($directory.'/composer.lock', $lock);
    $composer = (new ExecutableFinder)->find('composer');
    expect($composer)->not->toBeNull();
    if (in_array(strtolower(pathinfo($composer, PATHINFO_EXTENSION)), ['bat', 'cmd'], true)) {
        // The Windows installer exposes a launcher beside the actual PHAR.
        $composer = dirname($composer).'/composer.phar';
        expect($composer)->toBeFile();
    }
    $command = [PHP_BINARY, $root.'/tools/ci/prepare-php86.php', $composer, $directory.'/metadata.json'];
    try {
        $initial = new Process($command, $directory);
        $initial->run();
        if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6) {
            expect($initial->getExitCode())->toBe(1)
                ->and($initial->getErrorOutput())->toContain('actual PHP 8.6 interpreter');
            $this->assertFileDoesNotExist($directory.'/metadata.json');

            return;
        }
        expect($initial->isSuccessful())->toBeTrue();
        $metadata = json_decode(file_get_contents($directory.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($metadata['originalManifestSha256'])->toBe(hash('sha256', $manifest))
            ->and($metadata['originalLockSha256'])->toBe(hash('sha256', $lock))
            ->and($metadata['manifestUnchanged'])->toBeTrue();
        $verify = new Process([...$command, '--verify'], $directory);
        $verify->mustRun();
        expect(file_get_contents($directory.'/composer.json'))->toBe($manifest)
            ->and(file_get_contents($directory.'/composer.lock'))->toBe($lock);
        $changed = json_decode($lock, true, 512, JSON_THROW_ON_ERROR);
        $changed['packages'][0]['description'] = 'Tampered package metadata';
        file_put_contents($directory.'/composer.lock', json_encode($changed, JSON_THROW_ON_ERROR));
        $verify->run();
        expect($verify->getExitCode())->toBe(1)
            ->and($verify->getErrorOutput())->toContain('changed during the check');
        file_put_contents($directory.'/composer.lock', $lock);
        file_put_contents($directory.'/composer.json', str_replace('~8.5.0 || ~8.6.0', '~8.5.0', $manifest));
        $verify->run();
        expect($verify->getExitCode())->toBe(1)
            ->and($verify->getErrorOutput())->toContain('does not match its manifest');
    } finally {
        foreach (['composer.json', 'composer.lock', 'metadata.json', 'metadata.json.composer.phar'] as $file) {
            if (is_file($directory.'/'.$file)) {
                unlink($directory.'/'.$file);
            }
        }
        rmdir($directory);
    }
});

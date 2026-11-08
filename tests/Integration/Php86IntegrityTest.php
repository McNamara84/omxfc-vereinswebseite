<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Php86IntegrityTest extends TestCase
{
    public function test_integrity_checks_preserve_the_manifest_and_detect_lockfile_tampering(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir().'/omxfc-integrity-'.bin2hex(random_bytes(12));
        mkdir($directory);
        $manifest = file_get_contents($root.'/composer.json');
        $lock = file_get_contents($root.'/composer.lock');
        file_put_contents($directory.'/composer.json', $manifest);
        file_put_contents($directory.'/composer.lock', $lock);
        $composer = (new ExecutableFinder)->find('composer');
        $this->assertNotNull($composer);
        if (in_array(strtolower(pathinfo($composer, PATHINFO_EXTENSION)), ['bat', 'cmd'], true)) {
            // The Windows installer exposes a launcher beside the actual PHAR.
            $composer = dirname($composer).'/composer.phar';
            $this->assertFileExists($composer);
        }
        $command = [PHP_BINARY, $root.'/tools/ci/prepare-php86.php', $composer, $directory.'/metadata.json'];

        try {
            $initial = new Process($command, $directory);
            $initial->run();
            if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6) {
                $this->assertSame(1, $initial->getExitCode());
                $this->assertStringContainsString('actual PHP 8.6 interpreter', $initial->getErrorOutput());
                $this->assertFileDoesNotExist($directory.'/metadata.json');

                return;
            }
            $this->assertTrue($initial->isSuccessful(), $initial->getErrorOutput());
            $metadata = json_decode(file_get_contents($directory.'/metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(hash('sha256', $manifest), $metadata['originalManifestSha256']);
            $this->assertSame(hash('sha256', $lock), $metadata['originalLockSha256']);
            $this->assertTrue($metadata['manifestUnchanged']);
            $verify = new Process([...$command, '--verify'], $directory);
            $verify->mustRun();
            $this->assertSame($manifest, file_get_contents($directory.'/composer.json'));
            $this->assertSame($lock, file_get_contents($directory.'/composer.lock'));

            $changed = json_decode($lock, true, 512, JSON_THROW_ON_ERROR);
            $changed['packages'][0]['description'] = 'Tampered package metadata';
            file_put_contents($directory.'/composer.lock', json_encode($changed, JSON_THROW_ON_ERROR));
            $verify->run();
            $this->assertSame(1, $verify->getExitCode());
            $this->assertStringContainsString('changed during the check', $verify->getErrorOutput());
            file_put_contents($directory.'/composer.lock', $lock);

            file_put_contents($directory.'/composer.json', str_replace('~8.5.0 || ~8.6.0', '~8.5.0', $manifest));
            $verify->run();
            $this->assertSame(1, $verify->getExitCode());
            $this->assertStringContainsString('does not match its manifest', $verify->getErrorOutput());
        } finally {
            foreach (['composer.json', 'composer.lock', 'metadata.json', 'metadata.json.composer.phar'] as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }
}

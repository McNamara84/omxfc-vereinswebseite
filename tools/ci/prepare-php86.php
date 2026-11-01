<?php

declare(strict_types=1);
use Composer\Package\Locker;

// Only root metadata changes. Never resolve or alter locked packages for this probe.
$exitCode = 0;

try {
    [$script, $composerPath, $metadataPath] = $argv;
    if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6) {
        throw new RuntimeException('The compatibility probe requires an actual PHP 8.6 interpreter.');
    }

    // The PHAR stream wrapper needs the .phar suffix even when Composer itself
    // is installed as /usr/bin/composer by Docker or setup-php.
    $composerArchive = $metadataPath.'.composer.phar';
    if (! copy($composerPath, $composerArchive)) {
        throw new RuntimeException('The verified Composer PHAR could not be prepared.');
    }
    require 'phar://'.$composerArchive.'/vendor/autoload.php';
    $originalManifest = file_get_contents('composer.json');
    $originalLock = file_get_contents('composer.lock');
    $manifest = json_decode($originalManifest, false, 512, JSON_THROW_ON_ERROR);
    $lock = json_decode($originalLock, false, 512, JSON_THROW_ON_ERROR);

    if (! hash_equals($lock->{'content-hash'}, Locker::getContentHash($originalManifest))) {
        throw new RuntimeException('The original Composer lockfile does not match its manifest.');
    }
    if (isset($manifest->config->platform->php)) {
        throw new RuntimeException('A simulated config.platform.php is not permitted in this probe.');
    }

    $packages = json_encode([$lock->packages, $lock->{'packages-dev'}], JSON_THROW_ON_ERROR);
    if (($argv[3] ?? '') === '--verify') {
        $metadata = json_decode(file_get_contents($metadataPath), false, 512, JSON_THROW_ON_ERROR);
        if (! hash_equals($metadata->packagesSha256, hash('sha256', $packages))
            || $manifest->require->php !== $metadata->probeConstraint
            || $lock->platform->php !== $metadata->probeConstraint
            || $metadata->php !== PHP_VERSION) {
            throw new RuntimeException('The prepared lockfile, package metadata or PHP interpreter changed during the probe.');
        }
        echo "Prepared lockfile and all locked package metadata remain unchanged.\n";
    } else {
        $originalConstraint = $manifest->require->php;
        $probeConstraint = $originalConstraint.' || ~8.6.0';
        $manifest->require->php = $probeConstraint;
        $lock->platform->php = $probeConstraint;
        $probeManifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $lock->{'content-hash'} = Locker::getContentHash($probeManifest);
        $probeLock = json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $verifiedLock = json_decode($probeLock, false, 512, JSON_THROW_ON_ERROR);
        if ($packages !== json_encode([$verifiedLock->packages, $verifiedLock->{'packages-dev'}], JSON_THROW_ON_ERROR)) {
            throw new RuntimeException('Locked package metadata changed unexpectedly.');
        }

        file_put_contents('composer.json', $probeManifest);
        file_put_contents('composer.lock', $probeLock);
        file_put_contents($metadataPath, json_encode([
            'php' => PHP_VERSION,
            'composer' => Composer\Composer::VERSION,
            'originalConstraint' => $originalConstraint,
            'probeConstraint' => $probeConstraint,
            'originalLockSha256' => hash('sha256', $originalLock),
            'packagesSha256' => hash('sha256', $packages),
            'packageCount' => count($lock->packages) + count($lock->{'packages-dev'}),
            'packagesUnchanged' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        echo "Only the root PHP constraint and its lock metadata were changed; all locked packages are unchanged.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Probe integrity: '.$error->getMessage()."\n");
    $exitCode = 1;
} finally {
    if (isset($composerArchive) && is_file($composerArchive)) {
        unlink($composerArchive);
    }
}

exit($exitCode);

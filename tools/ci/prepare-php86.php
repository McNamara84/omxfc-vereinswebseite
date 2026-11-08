<?php

declare(strict_types=1);

use Composer\Package\Locker;
use Composer\Semver\Semver;

// Verify the ordinary manifest and lockfile. The check never rewrites either.
$exitCode = 0;

try {
    if (count($argv) < 3) {
        throw new RuntimeException('Usage: prepare-php86.php <composer.phar> <metadata.json> [--verify]');
    }
    [, $composerPath, $metadataPath] = $argv;
    if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6) {
        throw new RuntimeException('The compatibility check requires an actual PHP 8.6 interpreter.');
    }

    $composerArchive = $metadataPath.'.composer.phar';
    if (! copy($composerPath, $composerArchive)) {
        throw new RuntimeException('The verified Composer PHAR could not be prepared.');
    }
    require 'phar://'.$composerArchive.'/vendor/autoload.php';

    $manifestText = file_get_contents('composer.json');
    $lockText = file_get_contents('composer.lock');
    $manifest = json_decode($manifestText, false, 512, JSON_THROW_ON_ERROR);
    $lock = json_decode($lockText, false, 512, JSON_THROW_ON_ERROR);
    if (! hash_equals($lock->{'content-hash'}, Locker::getContentHash($manifestText))) {
        throw new RuntimeException('The Composer lockfile does not match its manifest.');
    }
    if (isset($manifest->config->platform->php)) {
        throw new RuntimeException('A simulated config.platform.php is not permitted.');
    }
    $constraint = $manifest->require->php;
    if ($lock->platform->php !== $constraint
        || ! Semver::satisfies('8.5.0', $constraint)
        || ! Semver::satisfies('8.6.0', $constraint)) {
        throw new RuntimeException('Both PHP 8.5 and PHP 8.6 must be supported by the ordinary manifest and lockfile.');
    }

    $evidence = [
        'php' => PHP_VERSION,
        'composer' => Composer\Composer::VERSION,
        'phpConstraint' => $constraint,
        'originalManifestSha256' => hash('sha256', $manifestText),
        'originalLockSha256' => hash('sha256', $lockText),
        'packagesSha256' => hash('sha256', json_encode([$lock->packages, $lock->{'packages-dev'}], JSON_THROW_ON_ERROR)),
        'packageCount' => count($lock->packages) + count($lock->{'packages-dev'}),
        'packagesUnchanged' => true,
        'manifestUnchanged' => true,
    ];

    if (($argv[3] ?? '') === '--verify') {
        $metadata = json_decode(file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        if ($metadata !== $evidence) {
            throw new RuntimeException('The manifest, lockfile, package metadata or PHP interpreter changed during the check.');
        }
    } elseif (file_put_contents($metadataPath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
        throw new RuntimeException('The integrity evidence could not be written.');
    }
    echo "Manifest, lockfile and all locked package metadata remain unchanged.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Check integrity: '.$error->getMessage()."\n");
    $exitCode = 1;
} finally {
    if (isset($composerArchive) && is_file($composerArchive)) {
        unlink($composerArchive);
    }
}

exit($exitCode);

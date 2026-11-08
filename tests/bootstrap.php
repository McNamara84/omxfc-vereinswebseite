<?php

declare(strict_types=1);

// Laravel installs its own error handler. Keep its deprecation diagnostics even
// when PHPUnit cannot observe the original PHP error (including in workers).
$ownsDeprecationLog = ! getenv('PHP_DEPRECATIONS_LOG');
$deprecationLog = getenv('PHP_DEPRECATIONS_LOG') ?: tempnam(sys_get_temp_dir(), 'omxfc-deprecations-');
if ($deprecationLog === false) {
    throw new RuntimeException('Cannot create the PHP deprecation log.');
}
foreach ([$deprecationLog, $deprecationLog.'.php.log'] as $evidenceFile) {
    if (@file_put_contents($evidenceFile, '', FILE_APPEND) === false || ! is_readable($evidenceFile)) {
        throw new RuntimeException('Cannot prepare PHP deprecation evidence: '.$evidenceFile);
    }
}

putenv('PHP_DEPRECATIONS_LOG='.$deprecationLog);
putenv('LOG_DEPRECATIONS_CHANNEL=php_deprecations');
putenv('LOG_DEPRECATIONS_WHILE_TESTING=true');
putenv('LOG_DEPRECATIONS_TRACE=true');
$_ENV['PHP_DEPRECATIONS_LOG'] = $_SERVER['PHP_DEPRECATIONS_LOG'] = $deprecationLog;
$_ENV['LOG_DEPRECATIONS_CHANNEL'] = $_SERVER['LOG_DEPRECATIONS_CHANNEL'] = 'php_deprecations';
$_ENV['LOG_DEPRECATIONS_WHILE_TESTING'] = $_SERVER['LOG_DEPRECATIONS_WHILE_TESTING'] = 'true';
$_ENV['LOG_DEPRECATIONS_TRACE'] = $_SERVER['LOG_DEPRECATIONS_TRACE'] = 'true';
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', $deprecationLog.'.php.log');

register_shutdown_function(static function () use ($deprecationLog, $ownsDeprecationLog): void {
    clearstatcache();
    $laravel = is_file($deprecationLog) ? file_get_contents($deprecationLog) : false;
    $native = is_file($deprecationLog.'.php.log') ? file_get_contents($deprecationLog.'.php.log') : false;

    if ($laravel === false || $native === false) {
        file_put_contents('php://stderr', "PHP deprecation evidence could not be read.\n");
        exit(1);
    }

    if ($laravel !== '' || preg_match('/^(?:\[[^\r\n]*\]\s*)?(?:PHP\s+)?(?:Deprecated|User Deprecated):/mi', $native)) {
        file_put_contents('php://stderr', "PHP deprecations detected; see {$deprecationLog} and {$deprecationLog}.php.log\n".$laravel.$native);
        exit(1);
    }
    if ($ownsDeprecationLog) {
        unlink($deprecationLog);
        unlink($deprecationLog.'.php.log');
    }
});

require dirname(__DIR__).'/vendor/autoload.php';

// Fresh checkouts do not contain the private legacy metadata cache. Tests that
// replace its contents must have a baseline independent of test ordering.
$seriesFixtureDirectory = dirname(__DIR__).'/storage/app/private';
if (! is_dir($seriesFixtureDirectory) && ! mkdir($seriesFixtureDirectory, 0777, true) && ! is_dir($seriesFixtureDirectory)) {
    throw new RuntimeException('Cannot create the series metadata fixture directory.');
}
foreach (['maddrax', 'hardcovers', 'missionmars', 'volkdertiefe', '2012', 'abenteurer'] as $seriesFixtureName) {
    $seriesFixturePath = $seriesFixtureDirectory.'/'.$seriesFixtureName.'.json';
    if (! is_file($seriesFixturePath)) {
        $seriesFixtureStream = @fopen($seriesFixturePath, 'x');
        if ($seriesFixtureStream !== false) {
            fwrite($seriesFixtureStream, '[]');
            fclose($seriesFixtureStream);
        } elseif (! is_file($seriesFixturePath)) {
            throw new RuntimeException('Cannot prepare the series metadata fixture: '.$seriesFixtureName);
        }
    }
}

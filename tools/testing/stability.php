<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Pest's --retry sorts previous failures first; it does not repeat them.
// This explicit diagnostic command runs failing cases again in fresh processes.
$arguments = array_slice($argv, 1);
$mode = array_shift($arguments);
$option = array_shift($arguments);
if (! in_array($mode, ['repeat', 'retry'], true) || ! is_string($option) || ! preg_match('/^--attempts=([2-9]|10)$/D', $option, $match)) {
    fwrite(STDERR, "Usage: php tools/testing/stability.php <repeat|retry> --attempts=2 [Pest arguments]\n");
    exit(2);
}
$attempts = (int) $match[1];
foreach ($arguments as $argument) {
    if (preg_match('/^--(?:log-junit|retry|repeat|parallel|tia)(?:=|$)/', $argument)) {
        fwrite(STDERR, "Retry diagnostics require serial fresh execution and their own JUnit report.\n");
        exit(2);
    }
}
$report = tempnam(sys_get_temp_dir(), 'pest-retry-');
if ($report === false) {
    throw new RuntimeException('Cannot create the retry report.');
}

try {
    $status = 1;
    $firstFailure = 0;
    $filter = null;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        fwrite(STDOUT, "Diagnostic attempt {$attempt}/{$attempts}; a complete composer test run remains required.\n");
        file_put_contents($report, '');
        $selected = $arguments;
        if ($filter !== null) {
            $selected = [];
            for ($index = 0; $index < count($arguments); $index++) {
                if ($arguments[$index] === '--filter') {
                    $index++;
                } elseif (! str_starts_with($arguments[$index], '--filter=')) {
                    $selected[] = $arguments[$index];
                }
            }
            $selected[] = '--filter='.$filter;
        }
        $process = new Process([
            PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/pest', ...$selected,
            '--no-tia', '--fail-on-empty-test-suite', '--log-junit='.$report,
        ], dirname(__DIR__, 2));
        $process->setTimeout(null);
        $status = $process->run(static function (string $type, string $buffer): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
        });
        if ($status !== 0 && $firstFailure === 0) {
            $firstFailure = $status;
        }
        $xml = new DOMDocument;
        if (! $xml->load($report, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            fwrite(STDERR, "No complete diagnostic JUnit report was produced.\n");
            $status = $status ?: 1;
            break;
        }
        $xpath = new DOMXPath($xml);
        if ($xpath->evaluate('count(//testcase)') < 1) {
            $status = 1;
            break;
        }
        if ($mode === 'repeat') {
            continue;
        }
        if ($status === 0) {
            break;
        }
        $failures = [];
        foreach ($xpath->query('//testcase[failure or error]') as $case) {
            $failures[] = preg_quote($case->getAttribute('class').'::'.$case->getAttribute('name'), '~');
        }
        if ($failures === []) {
            break;
        }
        $filter = '~^(?:'.implode('|', array_unique($failures)).')$~u';
    }
} finally {
    unlink($report);
}

exit($mode === 'repeat' ? ($firstFailure ?: $status) : $status);

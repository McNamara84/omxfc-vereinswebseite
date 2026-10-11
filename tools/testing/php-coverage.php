<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\Node\Builder;
use SebastianBergmann\CodeCoverage\Node\File;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Serialization\Merger;
use SebastianBergmann\CodeCoverage\StaticAnalysis\FileAnalyser;
use SebastianBergmann\CodeCoverage\StaticAnalysis\ParsingSourceAnalyser;

require dirname(__DIR__, 2).'/vendor/autoload.php';
// Coverage artifacts retain per-test hit maps; merging them needs more memory
// than the test runner's default PHP CLI limit, without changing test limits.
ini_set('memory_limit', '1536M');

// Read only PHPUnit-generated artifacts from this revision's own test jobs.
// Committed baselines are inert JSON. PHPUnit 13 uses serialization format 4;
// its Merger validates runtime, driver and format and unions executed code.
[$script, $outputPath] = $argv + [null, null];
$inputs = array_slice($argv, 2);
$clover = null;
foreach ($inputs as $key => $input) {
    if (str_starts_with($input, '--clover=')) {
        $clover = substr($input, strlen('--clover='));
        unset($inputs[$key]);
    }
}
if (! $outputPath || $inputs === []) {
    fwrite(STDERR, "Usage: php php-coverage.php <snapshot.json> <coverage.php> [...] [--clover=coverage.xml]\n");
    exit(1);
}
$merged = (new Merger)->merge($inputs);
$report = (new Builder(new FileAnalyser(new ParsingSourceAnalyser, false, false)))->build(
    $merged['codeCoverage'], $merged['testResults'], $merged['basePath']
);
foreach ($merged['testResults'] as $result) {
    if (! in_array($result['status'], ['success', 'skipped'], true)) {
        throw new RuntimeException('Coverage requires a completed successful test run: '.$result['status']);
    }
}
$root = str_replace('\\', '/', dirname(__DIR__, 2)).'/';
$files = [];
$totals = ['lines' => ['total' => 0, 'covered' => 0], 'functions' => ['total' => 0, 'covered' => 0]];
foreach ($report as $file) {
    if (! $file instanceof File) {
        continue;
    }
    $absolute = str_replace('\\', '/', $file->pathAsString());
    if (! str_starts_with($absolute, $root)) {
        throw new RuntimeException('Coverage source is outside the project: '.$absolute);
    }
    $relative = substr($absolute, strlen($root));
    $units = ['lines' => [], 'functions' => []];
    foreach ($file->lineCoverageData() as $line => $hits) {
        if ($hits !== null) {
            $units['lines'][(string) $line] = array_sum($hits) > 0;
        }
    }
    foreach ($file->classesAndTraits() as $className => $class) {
        foreach ($class->methods as $methodName => $method) {
            if ($method->executableLines > 0) {
                // PHP's method metric requires all executable lines covered.
                $units['functions'][$className.'::'.$methodName.':'.$method->startLine] = $method->coverage === 100;
            }
        }
    }
    $metrics = [];
    foreach ($units as $name => $locations) {
        $metrics[$name] = ['total' => count($locations), 'covered' => count(array_filter($locations))];
        $totals[$name]['total'] += $metrics[$name]['total'];
        $totals[$name]['covered'] += $metrics[$name]['covered'];
        $units[$name] = (object) $locations;
    }
    if ($metrics['lines']['total'] !== $file->numberOfExecutableLines() || $metrics['lines']['covered'] !== $file->numberOfExecutedLines()
        || $metrics['functions']['total'] !== $file->numberOfMethods() || $metrics['functions']['covered'] !== $file->numberOfTestedMethods()) {
        throw new RuntimeException('Incomplete coverage locations: '.$relative);
    }
    $files[$relative] = ['hash' => hash('sha256', str_replace("\r\n", "\n", file_get_contents($absolute))), 'metrics' => $metrics, 'units' => $units];
}
if ($files === []) {
    throw new RuntimeException('Coverage report contains no source files');
}
ksort($files);
$snapshot = ['schema' => 1, 'language' => 'php', 'profile' => 'phpunit-xdebug-unit-integration-feature', 'metadata' => ['runtime' => $merged['buildInformation']['runtime'], 'driver' => $merged['buildInformation']['phpCodeCoverage']['driverInformation']], 'metrics' => $totals, 'files' => $files];
if (! is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0777, true);
}
file_put_contents($outputPath, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
if ($clover !== null) {
    (new Clover)->process($report, $clover);
}

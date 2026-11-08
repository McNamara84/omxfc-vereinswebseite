<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('tia baseline workflow records a fresh graph with the canonical composer script', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = file_get_contents($root.'/.github/workflows/tia-baseline.yml');
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $tiaConfiguration = file_get_contents($root.'/phpunit.tia.xml');
    expect($workflow)->toBeString()
        ->and($tiaConfiguration)->toBeString();
    // The publisher must record without a previous baseline or gh session.
    expect($workflow)->toMatch('/^\s*run: composer test:tia:fresh\s*$/m');
    $command = $composer['scripts']['test:tia:fresh'] ?? null;
    expect($command)->toBeString()
        ->toContain('auto_prepend_file=tests/tia-filter.php')
        ->toContain('--tia')
        ->toContain('--fresh')->not->toContain('--random-order-seed')->not->toContain('--order-by=random')
        ->toContain('--configuration phpunit.tia.xml')
        ->and(strstr($command, 'vendor/bin/pest '))->toBeString()->not->toContain('tests/');
    $xml = new \DOMDocument;
    expect($xml->loadXML($tiaConfiguration, LIBXML_NONET))->toBeTrue();
    $xpath = new \DOMXPath($xml);
    expect($xpath->evaluate('string(/phpunit/@bootstrap)'))->toBe('tests/tia-bootstrap.php');
    $directories = array_map(static fn (\DOMNode $node): string => $node->textContent, iterator_to_array($xpath->query('//testsuite/directory')));
    expect($directories)->toBe(['tests/Unit', 'tests/Feature', 'tests/Integration']);
    $excluded = array_map(static fn (\DOMNode $node): string => $node->textContent, iterator_to_array($xpath->query('//testsuite/exclude')));
    expect($excluded)->toContain('tests/Integration/TypesenseInternalSnapshotTest.php');
    foreach (glob($root.'/tests/Feature/*MariaDb*Test.php') as $workerTest) {
        expect($excluded)->toContain('tests/Feature/'.basename($workerTest));
    }
    $replayCommand = $composer['scripts']['test:tia'] ?? null;
    expect($replayCommand)->toBeString()
        ->toContain('--tia')->not->toContain('--fresh')
        ->toContain('--configuration phpunit.tia.xml')
        ->and(strstr($replayCommand, 'vendor/bin/pest '))->toBeString()->not->toContain('tests/');
});

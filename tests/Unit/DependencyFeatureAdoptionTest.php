<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('phpunit stability diagnostics are available without masking the default test command', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $scripts = $composer['scripts'] ?? [];
    expect($scripts['test'] ?? null)->toBe('@php vendor/bin/pest --compact --timeout=1800')
        ->and($scripts['test'])->not->toContain('--retry')
        ->and($scripts['test:stability:repeat'] ?? '')->toContain('tools/testing/stability.php repeat --attempts=2')
        ->and($scripts['test:stability:retry'] ?? '')->toContain('tools/testing/stability.php retry --attempts=2');
});

test('application assets do not depend on remote font or icon stylesheets', function (): void {
    $root = dirname(__DIR__, 2);
    $layouts = implode('', array_map(static fn (string $layout): string => (string) file_get_contents($layout), glob($root.'/resources/views/layouts/*.blade.php') ?: []));
    $memberMap = (string) file_get_contents($root.'/resources/views/mitglieder/karte.blade.php');
    expect($layouts)->not->toContain('fonts.bunny.net')
        ->and($memberMap)->not->toContain('cdnjs.cloudflare.com/ajax/libs/font-awesome');
});

test('deployment keeps writes paused until health checks pass', function (): void {
    $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
    $featureCheck = strpos($workflow, 'php artisan queue:pause --help --no-ansi');
    $pause = strpos($workflow, 'php artisan queue:pause --all');
    $stop = strpos($workflow, '$COMPOSE stop --timeout 360 queue');
    $resume = strpos($workflow, 'docker exec maddrax-app php artisan queue:resume --all');
    $start = strpos($workflow, '$COMPOSE up -d --force-recreate --no-deps queue scheduler');
    $health = strpos($workflow, 'docker exec maddrax-nginx wget -q -O /dev/null http://127.0.0.1/up');
    $release = strpos($workflow, 'docker exec maddrax-app php artisan up');
    expect($featureCheck)->not->toBeFalse()
        ->and($pause)->not->toBeFalse()
        ->and($stop)->not->toBeFalse()
        ->and($resume)->not->toBeFalse()
        ->and($start)->not->toBeFalse()
        ->and($health)->not->toBeFalse()
        ->and($release)->not->toBeFalse()
        ->and($featureCheck)->toBeLessThan($pause)
        ->and($pause)->toBeLessThan($stop)
        ->and($stop)->toBeLessThan($resume)
        ->and($start)->toBeLessThan($health)
        ->and($health)->toBeLessThan($resume)
        ->and($resume)->toBeLessThan($release);
});

test('deployment prunes only stale dangling images after health checks', function (): void {
    $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
    // nginx no longer publishes a host port (Traefik is the only entrypoint), so the
    // connectivity check goes through the nginx container directly and via Traefik/TLS.
    $connectivityCheck = strpos($workflow, 'docker exec maddrax-nginx wget -q -O /dev/null http://127.0.0.1/');
    $imagePrune = strpos($workflow, 'docker image prune --force --filter "until=168h"');
    $retention = strpos($workflow, 'cleanup_deployment_retention');
    expect($connectivityCheck)->not->toBeFalse()
        ->and($imagePrune)->not->toBeFalse()
        ->and($retention)->not->toBeFalse()
        ->and($connectivityCheck)->toBeLessThan($imagePrune)
        ->toBeLessThan($retention)
        ->and($workflow)->not->toContain('docker volume prune')->not->toContain('docker system prune');
});

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('matching localized names are healthy and extra namespaces are ignored', function (): void {
    config(['maddraxikon.allowed_namespaces' => [0, 10, 14], 'maddraxikon.expected_namespace_names' => [0 => '', 10 => 'Vorlage', 14 => 'Kategorie']]);
    $apiClient = Mockery::mock(MaddraxikonApiClient::class);
    $apiClient->expects('namespaces')->once()->andReturn([0 => '', 2 => 'Benutzer', 10 => 'Vorlage', 14 => 'Kategorie']);
    $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();
    expect($report['healthy'])->toBeTrue()
        ->and($report['missing'])->toBe([])
        ->and($report['mismatched'])->toBe([])
        ->and($report['actual'])->toHaveKey(2)
        ->and($report['expected'])->not->toHaveKey(2);
});

test('missing and renamed allowed namespaces are reported by id', function (): void {
    config(['maddraxikon.allowed_namespaces' => [0, 10, 14, 420], 'maddraxikon.expected_namespace_names' => [0 => '', 10 => 'Vorlage', 14 => 'Kategorie', 420 => 'GeoJson']]);
    $apiClient = Mockery::mock(MaddraxikonApiClient::class);
    $apiClient->expects('namespaces')->once()->andReturn([0 => '', 10 => 'Template', 14 => 'Kategorie']);
    $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();
    expect($report['healthy'])->toBeFalse()
        ->and($report['missing'])->toBe([420 => 'GeoJson'])
        ->and($report['mismatched'])->toBe([10 => ['expected' => 'Vorlage', 'actual' => 'Template']]);
});

test('only allowed namespaces are expected', function (): void {
    config(['maddraxikon.allowed_namespaces' => [14, 14, 999], 'maddraxikon.expected_namespace_names' => [0 => '', 14 => 'Kategorie']]);
    $apiClient = Mockery::mock(MaddraxikonApiClient::class);
    $apiClient->expects('namespaces')->once()->andReturn([14 => 'Kategorie']);
    $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();
    expect($report['healthy'])->toBeTrue()
        ->and($report['expected'])->toBe([14 => 'Kategorie']);
});

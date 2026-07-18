<?php

namespace Tests\Unit;

use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MaddraxikonNamespaceHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_localized_names_are_healthy_and_extra_namespaces_are_ignored(): void
    {
        config([
            'maddraxikon.allowed_namespaces' => [0, 10, 14],
            'maddraxikon.expected_namespace_names' => [
                0 => '',
                10 => 'Vorlage',
                14 => 'Kategorie',
            ],
        ]);

        $apiClient = Mockery::mock(MaddraxikonApiClient::class);
        $apiClient->expects('namespaces')->once()->andReturn([
            0 => '',
            2 => 'Benutzer',
            10 => 'Vorlage',
            14 => 'Kategorie',
        ]);

        $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();

        $this->assertTrue($report['healthy']);
        $this->assertSame([], $report['missing']);
        $this->assertSame([], $report['mismatched']);
        $this->assertArrayHasKey(2, $report['actual']);
        $this->assertArrayNotHasKey(2, $report['expected']);
    }

    public function test_missing_and_renamed_allowed_namespaces_are_reported_by_id(): void
    {
        config([
            'maddraxikon.allowed_namespaces' => [0, 10, 14, 420],
            'maddraxikon.expected_namespace_names' => [
                0 => '',
                10 => 'Vorlage',
                14 => 'Kategorie',
                420 => 'GeoJson',
            ],
        ]);

        $apiClient = Mockery::mock(MaddraxikonApiClient::class);
        $apiClient->expects('namespaces')->once()->andReturn([
            0 => '',
            10 => 'Template',
            14 => 'Kategorie',
        ]);

        $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();

        $this->assertFalse($report['healthy']);
        $this->assertSame([420 => 'GeoJson'], $report['missing']);
        $this->assertSame([
            10 => [
                'expected' => 'Vorlage',
                'actual' => 'Template',
            ],
        ], $report['mismatched']);
    }

    public function test_only_allowed_namespaces_are_expected(): void
    {
        config([
            'maddraxikon.allowed_namespaces' => [14, 14, 999],
            'maddraxikon.expected_namespace_names' => [
                0 => '',
                14 => 'Kategorie',
            ],
        ]);

        $apiClient = Mockery::mock(MaddraxikonApiClient::class);
        $apiClient->expects('namespaces')->once()->andReturn([
            14 => 'Kategorie',
        ]);

        $report = (new MaddraxikonNamespaceHealthService($apiClient))->check();

        $this->assertTrue($report['healthy']);
        $this->assertSame([14 => 'Kategorie'], $report['expected']);
    }
}

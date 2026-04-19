<?php

namespace Tests\Unit;

use App\Http\Controllers\PageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

#[CoversClass(PageController::class)]
class PageControllerProtokolleTest extends TestCase
{
    use RefreshDatabase;

    public function test_protokolle_view_contains_expected_structure(): void
    {
        $controller = new PageController;

        $response = $controller->protokolle();

        $this->assertSame('pages.protokolle', $response->name());

        $viewData = $response->getData()['protokolle'] ?? [];

        $this->assertArrayHasKey(2023, $viewData);
        $this->assertSame('Gründungsversammlung', $viewData[2023][0]['titel']);
        $this->assertArrayHasKey(2024, $viewData);
        $this->assertCount(3, $viewData[2024]);
        $this->assertArrayHasKey(2025, $viewData);
        $this->assertCount(2, $viewData[2025]);
        $this->assertSame('Außerordentliche Mitgliederversammlung', $viewData[2025][1]['titel']);
        $this->assertArrayHasKey(2026, $viewData);
        $this->assertSame('Außerordentliche Mitgliederversammlung', $viewData[2026][0]['titel']);

        $years = array_keys($viewData);
        $this->assertArraysAreIdentical([2026, 2025, 2024, 2023], $years);
    }

    public function test_download_protokoll_streams_file_from_private_disk(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('protokolle/example.pdf', 'demo');

        $controller = new PageController;

        $response = $controller->downloadProtokoll('example.pdf');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('example.pdf', $response->headers->get('content-disposition'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\Controllers\PageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(PageController::class);

test('protokolle view contains expected structure', function (): void {
    $controller = new PageController;
    $response = $controller->protokolle();
    expect($response->name())->toBe('pages.protokolle');
    $viewData = $response->getData()['protokolle'] ?? [];
    expect($viewData)->toHaveKey(2023)
        ->and($viewData[2023][0]['titel'])->toBe('Gründungsversammlung')
        ->and($viewData)->toHaveKey(2024)
        ->and($viewData[2024])->toHaveCount(3)
        ->and($viewData)->toHaveKey(2025)
        ->and($viewData[2025])->toHaveCount(2)
        ->and($viewData[2025][1]['titel'])->toBe('Außerordentliche Mitgliederversammlung')
        ->and($viewData)->toHaveKey(2026)
        ->and($viewData[2026])->toHaveCount(2)
        ->and($viewData[2026][0]['titel'])->toBe('Außerordentliche Mitgliederversammlung')
        ->and($viewData[2026][1])->toBe(['datum' => '15. Juli 2026', 'titel' => 'Jahreshauptversammlung', 'datei' => '2026-07-15-jhv.pdf']);
    $years = array_keys($viewData);
    $this->assertArraysAreIdentical([2026, 2025, 2024, 2023], $years);
});

test('download protokoll streams file from private disk', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('protokolle/example.pdf', 'demo');
    $controller = new PageController;
    $response = $controller->downloadProtokoll('example.pdf');
    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-disposition'))->toContain('example.pdf');
});

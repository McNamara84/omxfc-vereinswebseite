<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('guest is redirected from protokolle', function (): void {
    $response = $this->get('/protokolle');
    $response->assertRedirect('/login');
});

test('protokolle page is accessible', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->get('/protokolle');
    $response->assertOk();
    $response->assertSee('Gründungsversammlung');
});

test('protokolle page shows document count and years', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->get('/protokolle');
    $response->assertOk();
    $response->assertViewHas('protokolle', fn ($protokolle) => isset($protokolle[2024], $protokolle[2026]) && count($protokolle[2024]) === 3 && count($protokolle[2026]) === 2 && $protokolle[2026][1]['datei'] === '2026-07-15-jhv.pdf');
    $response->assertSee('8 Dokumente');
    $response->assertSee('3 Dokumente');
    $response->assertSee('Protokolle 2025');
    $response->assertSeeText('15. Juli 2026 – Jahreshauptversammlung');
});

test('jhv 2026 protokoll can be downloaded', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('protokolle/2026-07-15-jhv.pdf', "%PDF-1.7\n%%EOF\n");
    $this->actingAs($this->actingMember());
    $response = $this->get('/protokolle/download/2026-07-15-jhv.pdf');
    $response->assertOk();
    $response->assertDownload('2026-07-15-jhv.pdf');
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('protokoll can be downloaded when file exists', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('protokolle/test.pdf', 'dummy');
    $this->actingAs($this->actingMember());
    $response = $this->get('/protokolle/download/test.pdf');
    $response->assertOk();
    $response->assertDownload('test.pdf');
});

test('error when protokoll is missing', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->from('/protokolle')->get('/protokolle/download/missing.pdf');
    $response->assertRedirect('/protokolle');
    $response->assertSessionHasErrors();
});

test('protokolle download requires authentication', function (): void {
    $response = $this->get('/protokolle/download/test.pdf');
    $response->assertRedirect('/login');
});

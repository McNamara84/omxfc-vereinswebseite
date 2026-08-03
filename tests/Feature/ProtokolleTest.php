<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class ProtokolleTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protokolle(): void
    {
        $response = $this->get('/protokolle');

        $response->assertRedirect('/login');
    }

    public function test_protokolle_page_is_accessible(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle');

        $response->assertOk();
        $response->assertSee('Gründungsversammlung');
    }

    public function test_protokolle_page_shows_document_count_and_years(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle');

        $response->assertOk();
        $response->assertViewHas('protokolle', function ($protokolle) {
            return isset($protokolle[2024], $protokolle[2026])
                && count($protokolle[2024]) === 3
                && count($protokolle[2026]) === 2
                && $protokolle[2026][1]['datei'] === '2026-07-15-jhv.pdf';
        });

        $response->assertSee('8 Dokumente');
        $response->assertSee('3 Dokumente');
        $response->assertSee('Protokolle 2025');
        $response->assertSeeText('15. Juli 2026 – Jahreshauptversammlung');
    }

    public function test_jhv_2026_protokoll_can_be_downloaded(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle/download/2026-07-15-jhv.pdf');

        $response->assertOk();
        $response->assertDownload('2026-07-15-jhv.pdf');
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_protokoll_can_be_downloaded_when_file_exists(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('protokolle/test.pdf', 'dummy');

        $this->actingAs($this->actingMember());

        $response = $this->get('/protokolle/download/test.pdf');

        $response->assertOk();
        $response->assertDownload('test.pdf');
    }

    public function test_error_when_protokoll_is_missing(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->from('/protokolle')->get('/protokolle/download/missing.pdf');

        $response->assertRedirect('/protokolle');
        $response->assertSessionHasErrors();
    }

    public function test_protokolle_download_requires_authentication(): void
    {
        $response = $this->get('/protokolle/download/test.pdf');

        $response->assertRedirect('/login');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtokolleTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

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
            return isset($protokolle[2024]) && count($protokolle[2024]) === 3;
        });

        $response->assertSee('3 Dokumente');
        $response->assertSee('Protokolle 2025');
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

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class ProtokollePageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_protokolle_page_shows_context_panels_and_archive_heading(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->withoutVite()->get('/protokolle');

        $response->assertOk();
        $response->assertSeeText('Protokolle');
        $response->assertSeeText('Archiv nach Jahren');
        $response->assertSeeText('Was hier abgelegt ist');
        $response->assertSeeText('Download-Hinweis');
    }
}
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class KompendiumPageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_kompendium_page_shows_context_panels_for_members(): void
    {
        $this->actingMember();

        $response = $this->withoutVite()->get('/kompendium');

        $response->assertOk();
        $response->assertSeeText('Maddrax-Kompendium');
        $response->assertSeeText('Indexierte Reihen');
        $response->assertSeeText('Zugangsmodell');
        $response->assertSeeText('Aktueller Stand');
    }
}
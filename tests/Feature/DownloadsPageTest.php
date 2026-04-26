<?php

namespace Tests\Feature;

use App\Models\Download;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class DownloadsPageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_downloads_page_shows_member_context_and_category_sections(): void
    {
        $this->actingMember();

        Download::factory()->create([
            'title' => 'Bauanleitung A',
            'category' => 'Klemmbaustein-Anleitungen',
            'description' => 'Anleitung für ein neues Clubprojekt.',
        ]);

        $response = $this->withoutVite()->get('/downloads');

        $response->assertOk();
        $response->assertSeeText('Downloads');
        $response->assertSeeText('Download-Bibliothek');
        $response->assertSeeText('Zugriff und Freischaltung');
        $response->assertSeeText('Was du hier findest');
        $response->assertSeeText('Klemmbaustein-Anleitungen');
    }
}
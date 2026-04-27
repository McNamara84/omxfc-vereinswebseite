<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PhotoGalleryPageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_photo_gallery_page_shows_context_and_year_overview(): void
    {
        Http::fake([
            'cloud.maddrax-fanclub.de/*1.jpg' => Http::response('', 200),
            'cloud.maddrax-fanclub.de/*2.jpg' => Http::response('', 404),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->withoutVite()->get('/fotogalerie');

        $response->assertOk();
        $response->assertSeeText('Fotogalerie');
        $response->assertSeeText('Galerieansicht');
        $response->assertSeeText('Jahre im Überblick');
        $response->assertSeeText('Hinweise zur Galerie');
        $response->assertSeeText('Fotos 2025');
    }
}
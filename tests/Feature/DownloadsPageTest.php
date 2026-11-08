<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Download;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('downloads page shows member context and category sections', function (): void {
    $this->actingMember();
    Download::factory()->create(['title' => 'Bauanleitung A', 'category' => 'Klemmbaustein-Anleitungen', 'description' => 'Anleitung für ein neues Clubprojekt.']);
    $response = $this->withoutVite()->get('/downloads');
    $response->assertOk();
    $response->assertSeeText('Downloads');
    $response->assertSeeText('Download-Bibliothek');
    $response->assertSeeText('Zugriff und Freischaltung');
    $response->assertSeeText('Was du hier findest');
    $response->assertSeeText('Klemmbaustein-Anleitungen');
});

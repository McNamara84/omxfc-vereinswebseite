<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class ArbeitsgruppenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbeitsgruppen_page_shows_information_architecture_and_single_main_heading(): void
    {
        $leader = User::factory()->create(['name' => 'Leitung Test']);

        Team::factory()->create([
            'name' => 'AG Test',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'Beschreibung der AG',
            'meeting_schedule' => 'monatlich online',
            'email' => 'ag-test@example.com',
        ]);

        $response = $this->withoutVite()->get('/arbeitsgruppen');

        $response->assertOk();
        $response->assertSeeText('Arbeitsgruppen des OMXFC e.V.');
        $response->assertSeeText('So funktionieren die AGs');
        $response->assertSeeText('Mitmachen');
        $response->assertSeeText('AG Test');
        $response->assertSeeText('Leitung Test');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}
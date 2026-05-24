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
        $leader = User::factory()->create([
            'name' => 'Leitung Test',
            'vorname' => 'Leitung',
        ]);

        $ag = Team::factory()->create([
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
        $response->assertSeeText('Leitung');
        $response->assertDontSeeText('Leitung Test');
        $response->assertSeeText('Kontakt aufnehmen');
        $response->assertSee('href="'.route('arbeitsgruppen.kontakt', $ag).'"', false);
        $response->assertSee('aria-label="Kontakt zur Arbeitsgruppe AG Test aufnehmen"', false);
        $response->assertDontSee('ag-test@example.com', false);
        $response->assertDontSee('mailto:ag-test@example.com', false);
        $response->assertSee('sm:grid-cols-2', false);
        $response->assertDontSee('xl:grid-cols-3', false);
        $response->assertSee('sm:col-span-2', false);
        $response->assertDontSee('data-testid="ag-logo-stage"', false);
        $response->assertDontSee('data-testid="ag-logo-image"', false);
        $response->assertDontSee('lg:grid-cols-[minmax(16rem,0.78fr)_minmax(0,1fr)]', false);

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }

    public function test_arbeitsgruppen_page_renders_logos_in_a_contain_stage_without_crop_contract(): void
    {
        $leader = User::factory()->create([
            'name' => 'Logo Leitung',
            'vorname' => 'Logo',
        ]);

        $ag = Team::factory()->create([
            'name' => 'AG mit Logo',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'Beschreibung mit Logo',
            'meeting_schedule' => 'alle zwei Wochen',
            'email' => 'logo-ag@example.com',
            'logo_path' => 'ag-logos/test-logo.png',
        ]);

        $response = $this->withoutVite()->get('/arbeitsgruppen');

        $response->assertOk();
        $response->assertSee('data-testid="ag-logo-stage"', false);
        $response->assertSee('data-testid="ag-logo-image"', false);
        $response->assertSee('src="'.asset('storage/'.$ag->logo_path).'"', false);
        $response->assertSee('alt="Logo der AG mit Logo"', false);
        $response->assertSee('object-contain', false);
        $response->assertDontSee('object-cover', false);
        $response->assertDontSee('class="ag-logo', false);
        $response->assertSee('lg:grid-cols-[minmax(16rem,0.78fr)_minmax(0,1fr)]', false);

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('[data-testid="ag-logo-stage"]'));
        $this->assertCount(1, $crawler->filter('[data-testid="ag-logo-image"]'));
    }
}
<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_cards_with_screenreader_texts(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        $expectedTitles = [
            'Offene Challenges',
            'Meine Baxx',
            'Matches in Tauschbörse',
            'Angebote in der Tauschbörse',
            'Meine Rezensionen',
            'Meine Kommentare',
        ];

        $crawler = new Crawler($response->getContent());
        $cardsContainer = $crawler->filter('div[aria-label="Überblick wichtiger Community-Kennzahlen"]');
        $this->assertCount(1, $cardsContainer, 'Dashboard card container missing');
        $this->assertStringContainsString('md:grid-cols-2', $cardsContainer->attr('class'));
        $this->assertStringContainsString('grid-flow-row-dense', $cardsContainer->attr('class'));
        $cards = $cardsContainer->filter('[role="region"]');
        $this->assertCount(count($expectedTitles), $cards, 'Unexpected number of dashboard cards rendered');
        foreach ($expectedTitles as $title) {
            $card = $cards->reduce(function (Crawler $node) use ($title) {
                return $node->filter('h2')->count() && trim($node->filter('h2')->text()) === $title;
            });
            $this->assertCount(1, $card, "Card {$title} missing");
            $headingId = $card->filter('h2')->attr('id');
            $this->assertNotEmpty($headingId);
            $this->assertEquals($headingId, $card->attr('aria-labelledby'));
            $this->assertGreaterThan(0, $card->filter('.sr-only')->count());
        }
    }
}

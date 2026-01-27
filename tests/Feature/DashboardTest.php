<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

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
            'Fanfiction',
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

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    public function test_dashboard_shows_applicants_for_privileged_roles(Role $role): void
    {
        $team = Team::membersTeam();
        $user = $this->createUserWithRole($role);
        $applicant = User::factory()->create();
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Mitgliedsanträge');
        $response->assertSeeText($applicant->name);

        $crawler = new Crawler($response->getContent());
        $table = $crawler->filter('table');
        $this->assertGreaterThan(0, $table->count());
        $this->assertSame('Name', trim($table->first()->filter('th')->eq(0)->text()));
        $this->assertSame('Genehmigen', trim($table->first()->filter('button')->eq(0)->text()));
    }

    public function test_dashboard_hides_applicants_for_regular_members(): void
    {
        $team = Team::membersTeam();
        $user = $this->createUserWithRole(Role::Mitglied);
        $applicant = User::factory()->create();
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Mitgliedsanträge');
        $response->assertDontSee($applicant->name);
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    public function test_dashboard_shows_pending_verification_card(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $team = Team::membersTeam();

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Abschließen',
            'description' => 'Wartet auf Verifizierung',
            'points' => 5,
            'status' => TodoStatus::Completed,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Auf Verifizierung wartende Challenges');
        $response->assertSeeText('Challenge(s)');
    }

    public function test_dashboard_hides_pending_verification_card_for_members(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $team = Team::membersTeam();

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Verifizierung wartet',
            'description' => 'Soll nicht sichtbar sein',
            'points' => 5,
            'status' => TodoStatus::Completed,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Auf Verifizierung wartende Challenges');
    }

    public function test_dashboard_displays_top_user_summary(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();

        $topUsers = User::factory()->count(3)->create(['current_team_id' => $team->id]);

        foreach ($topUsers as $index => $topUser) {
            $team->users()->attach($topUser, ['role' => Role::Mitglied->value]);
            UserPoint::create([
                'user_id' => $topUser->id,
                'team_id' => $team->id,
                'points' => 100 - ($index * 10),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        $crawler = new Crawler($response->getContent());
        $topList = $crawler->filter('[data-dashboard-top-users]');
        $this->assertSame(1, $topList->count());
        $this->assertStringContainsString('Top 3 Baxx-Sammler', $topList->attr('aria-label'));
        $this->assertSame(3, $topList->filter('[data-dashboard-top-user-item]')->count());
        $srSummary = $topList->filter('[data-dashboard-top-summary]');
        $this->assertSame(1, $srSummary->count());
        $this->assertStringContainsString('Top 3 Baxx-Sammler', trim($srSummary->text()));
    }
}

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
use Illuminate\Support\Facades\Config;
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
        Config::set('app.testing_minimal_layout', true);
    }

    private function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_dashboard_renders_three_compact_metric_groups_with_four_metrics_each(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        $expectedGroups = [
            'important' => 'Jetzt wichtig',
            'progress' => 'Mein Fortschritt',
            'community' => 'Community',
        ];

        $crawler = new Crawler($response->getContent());
        $groups = $crawler->filter('[data-testid^="dashboard-metric-group-"]');
        $this->assertCount(3, $groups);

        foreach ($expectedGroups as $key => $title) {
            $group = $crawler->filter("[data-testid=\"dashboard-metric-group-{$key}\"]");
            $this->assertCount(1, $group);
            $this->assertSame($title, trim($group->filter('h2')->text()));
            $this->assertSame($group->filter('h2')->attr('id'), $group->attr('aria-labelledby'));
            $this->assertCount(4, $group->filter('[data-testid^="dashboard-metric-"]:not([data-testid^="dashboard-metric-group-"])'));
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
    public function test_dashboard_shows_pending_verification_task(Role $role): void
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
        $response->assertSeeText('1 Verifizierung');
        $response->assertSeeText('Abgeschlossene Challenges warten auf Freigabe.');

        $crawler = new Crawler($response->getContent());
        $verificationTask = $crawler->filter('[data-testid="dashboard-task-verification"]');
        $this->assertCount(1, $verificationTask);
        $this->assertSame(route('todos.index', ['filter' => 'pending']), $verificationTask->attr('href'));
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
        $response->assertDontSee('dashboard-task-verification');
    }

    public function test_dashboard_displays_top_user_summary(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();

        $topUsers = User::factory()->count(3)->create(['current_team_id' => $team->id]);
        $topUsers->first()->update([
            'name' => 'Top Klarname',
            'alias' => 'TopNickname',
        ]);

        foreach ($topUsers as $index => $topUser) {
            $team->users()->attach($topUser, ['role' => Role::Mitglied->value]);
            UserPoint::create([
                'user_id' => $topUser->id,
                'team_id' => $team->id,
                'points' => [1234, 1040, 980][$index],
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        $crawler = new Crawler($response->getContent());
        $this->assertSame('Top 3 Baxx-Sammler', trim($crawler->filter('h2')->reduce(function (Crawler $node) {
            return trim($node->text()) === 'Top 3 Baxx-Sammler';
        })->text()));
        $topList = $crawler->filter('[data-dashboard-top-users]');
        $this->assertSame(1, $topList->count());
        $this->assertStringContainsString('Top 3 Baxx-Sammler', $topList->attr('aria-label'));
        $this->assertStringContainsString('1.234 Baxx', $topList->attr('aria-label'));
        $this->assertSame(3, $topList->filter('[data-dashboard-top-user-item]')->count());
        $srSummary = $topList->filter('[data-dashboard-top-summary]');
        $this->assertSame(1, $srSummary->count());
        $this->assertStringContainsString('Top 3 Baxx-Sammler', trim($srSummary->text()));
        $payload = json_decode($topList->attr('data-dashboard-top-users'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('TopNickname', $payload[0]['name']);
        $this->assertSame('1.234', $payload[0]['formatted_points']);
        $this->assertSame(1234, $payload[0]['points']);
    }

    public function test_dashboard_uses_dynamic_top_users_panel_title_for_shorter_rankings(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();

        $topUsers = User::factory()->count(2)->create(['current_team_id' => $team->id]);

        foreach ($topUsers as $index => $topUser) {
            $team->users()->attach($topUser, ['role' => Role::Mitglied->value]);
            UserPoint::create([
                'user_id' => $topUser->id,
                'team_id' => $team->id,
                'points' => [300, 220][$index],
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Top 2 Baxx-Sammler');
        $response->assertDontSeeText('TOP 3 Baxx-Sammler');
    }

    public function test_dashboard_shows_personalized_header_and_quick_actions_for_members(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create([
            'current_team_id' => $team->id,
            'vorname' => 'Alex',
            'alias' => 'DashboardNick',
        ]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Willkommen zurück, Alex');
        $response->assertDontSeeText('Willkommen zurück, DashboardNick');
        $response->assertSeeText('Schnellstart');
        $response->assertSeeText('Baxx verdienen');
        $response->assertSeeText('Veranstaltung');
        $response->assertDontSeeText('Fantreffen verwalten');
    }

    public function test_dashboard_prioritizes_governance_work_in_tasks_instead_of_quick_actions(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $applicant = User::factory()->create();
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Dashboard Quick Action',
            'description' => 'Soll als Schnellaktion auftauchen',
            'points' => 5,
            'status' => TodoStatus::Completed,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $quickActions = collect($response->viewData('quickActions'));
        $tasks = collect($response->viewData('tasks'));
        $earnBaxxAction = $quickActions->firstWhere('title', 'Baxx verdienen');
        $verificationTask = $tasks->firstWhere('key', 'verification');
        $applicantTask = $tasks->firstWhere('key', 'applicants');

        $response->assertOk();
        $response->assertSeeText('1 Mitgliedsantrag');
        $response->assertSeeText('1 Verifizierung');
        $this->assertNotNull($earnBaxxAction);
        $this->assertNull($quickActions->firstWhere('title', 'Verifizierungen prüfen'));
        $this->assertSame(route('todos.index', ['filter' => 'pending']), $verificationTask['href'] ?? null);
        $this->assertSame(1, $verificationTask['count'] ?? null);
        $this->assertSame('#dashboard-applicants', $applicantTask['href'] ?? null);
    }
}

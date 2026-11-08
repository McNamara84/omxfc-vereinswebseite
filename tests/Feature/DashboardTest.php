<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DashboardTestFixtures::class);

beforeEach(function (): void {
    Cache::flush();
    Config::set('app.testing_minimal_layout', true);
});

test('dashboard prioritizes quick actions and keeps all twelve metrics', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $expectedGroups = ['important' => 'Jetzt wichtig', 'progress' => 'Mein Fortschritt', 'community' => 'Community'];
    $crawler = new Crawler($response->getContent());
    $groups = $crawler->filter('[data-testid^="dashboard-metric-group-"]');
    expect($groups)->toHaveCount(3);
    foreach ($expectedGroups as $key => $title) {
        $group = $crawler->filter("[data-testid=\"dashboard-metric-group-{$key}\"]");
        expect($group)->toHaveCount(1);
        if ($key === 'important') {
            expect(trim($group->filter('h2')->text()))->toBe($title)
                ->and($group->attr('aria-labelledby'))->toBe($group->filter('h2')->attr('id'));
        } else {
            expect($group->attr('aria-label'))->toBe($title);
        }
        expect($group->filter('[data-testid^="dashboard-metric-"]:not([data-testid^="dashboard-metric-group-"])'))->toHaveCount($key === 'important' ? 2 : 4);
    }
    expect($crawler->filter('[data-testid^="dashboard-metric-"]:not([data-testid^="dashboard-metric-group-"])'))->toHaveCount(12)
        ->and(strpos($response->getContent(), 'dashboard-quick-actions'))->toBeLessThan(strpos($response->getContent(), 'dashboard-metric-group-important'));
});

test('dashboard shows applicants for privileged roles', function (Role $role): void {
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
    expect($table->count())->toBeGreaterThan(0)
        ->and(trim($table->first()->filter('th')->eq(0)->text()))->toBe('Name')
        ->and(trim($table->first()->filter('button')->eq(0)->text()))->toBe('Genehmigen');
})->with([[Role::Admin], [Role::Vorstand], [Role::Kassenwart]]);

test('dashboard hides applicants for regular members', function (): void {
    $team = Team::membersTeam();
    $user = $this->createUserWithRole(Role::Mitglied);
    $applicant = User::factory()->create();
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $response->assertDontSee('Mitgliedsanträge');
    $response->assertDontSee($applicant->name);
});

test('dashboard shows pending verification task', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $team = Team::membersTeam();
    Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'assigned_to' => $user->id, 'title' => 'Abschließen', 'description' => 'Wartet auf Verifizierung', 'points' => 5, 'status' => TodoStatus::Completed]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $response->assertSeeText('1 Verifizierung');
    $response->assertSeeText('Abgeschlossene Challenges warten auf Freigabe.');
    $crawler = new Crawler($response->getContent());
    $verificationTask = $crawler->filter('[data-testid="dashboard-task-verification"]');
    expect($verificationTask)->toHaveCount(1)
        ->and($verificationTask->attr('href'))->toBe(route('todos.index', ['filter' => 'pending']));
})->with([[Role::Admin], [Role::Vorstand], [Role::Kassenwart]]);

test('dashboard hides pending verification card for members', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $team = Team::membersTeam();
    Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'assigned_to' => $user->id, 'title' => 'Verifizierung wartet', 'description' => 'Soll nicht sichtbar sein', 'points' => 5, 'status' => TodoStatus::Completed]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $response->assertDontSee('dashboard-task-verification');
});

test('dashboard displays top user summary', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    $topUsers = User::factory()->count(3)->create(['current_team_id' => $team->id]);
    $topUsers->first()->update(['name' => 'Top Klarname', 'alias' => 'TopNickname']);
    foreach ($topUsers as $index => $topUser) {
        $team->users()->attach($topUser, ['role' => Role::Mitglied->value]);
        UserPoint::create(['user_id' => $topUser->id, 'team_id' => $team->id, 'points' => [1234, 1040, 980][$index]]);
    }
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    expect(trim($crawler->filter('h2')->reduce(fn (Crawler $node) => trim($node->text()) === 'Top 3 Baxx-Sammler')->text()))->toBe('Top 3 Baxx-Sammler');
    $topList = $crawler->filter('[data-dashboard-top-users]');
    expect($topList->count())->toBe(1)
        ->and($topList->attr('aria-label'))->toContain('Top 3 Baxx-Sammler')
        ->and($topList->attr('aria-label'))->toContain('1.234 Baxx')
        ->and($topList->filter('[data-dashboard-top-user-item]')->count())->toBe(3);
    $srSummary = $topList->filter('[data-dashboard-top-summary]');
    expect($srSummary->count())->toBe(1)
        ->and(trim($srSummary->text()))->toContain('Top 3 Baxx-Sammler');
    $payload = json_decode($topList->attr('data-dashboard-top-users'), true, flags: JSON_THROW_ON_ERROR);
    expect($payload[0]['name'])->toBe('TopNickname')
        ->and($payload[0]['formatted_points'])->toBe('1.234')
        ->and($payload[0]['points'])->toBe(1234);
});

test('dashboard uses dynamic top users panel title for shorter rankings', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    $topUsers = User::factory()->count(2)->create(['current_team_id' => $team->id]);
    foreach ($topUsers as $index => $topUser) {
        $team->users()->attach($topUser, ['role' => Role::Mitglied->value]);
        UserPoint::create(['user_id' => $topUser->id, 'team_id' => $team->id, 'points' => [300, 220][$index]]);
    }
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $response->assertSeeText('Top 2 Baxx-Sammler');
    $response->assertDontSeeText('TOP 3 Baxx-Sammler');
});

test('dashboard shows personalized header and quick actions for members', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id, 'vorname' => 'Alex', 'alias' => 'DashboardNick']);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $response->assertSeeText('Willkommen zurück, Alex');
    $response->assertDontSeeText('Willkommen zurück, DashboardNick');
    $response->assertSeeText('Schnellstart');
    $response->assertSeeText('Challenges & Baxx');
    $response->assertSeeText('Veranstaltung');
    $response->assertDontSeeText('Fantreffen verwalten');
});

test('dashboard prioritizes governance work in tasks instead of quick actions', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    $applicant = User::factory()->create();
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'assigned_to' => $user->id, 'title' => 'Dashboard Quick Action', 'description' => 'Soll als Schnellaktion auftauchen', 'points' => 5, 'status' => TodoStatus::Completed]);
    $response = $this->actingAs($user)->get('/dashboard');
    $quickActions = collect($response->viewData('quickActions'));
    $tasks = collect($response->viewData('tasks'));
    $earnBaxxAction = $quickActions->firstWhere('title', 'Challenges & Baxx');
    $verificationTask = $tasks->firstWhere('key', 'verification');
    $applicantTask = $tasks->firstWhere('key', 'applicants');
    $response->assertOk();
    $response->assertSeeText('1 Mitgliedsantrag');
    $response->assertSeeText('1 Verifizierung');
    expect($earnBaxxAction)->not->toBeNull()
        ->and($quickActions->firstWhere('title', 'Verifizierungen prüfen'))->toBeNull()
        ->and($verificationTask['href'] ?? null)->toBe(route('todos.index', ['filter' => 'pending']))
        ->and($verificationTask['count'] ?? null)->toBe(1)
        ->and($applicantTask['href'] ?? null)->toBe('#dashboard-applicants');
});

<?php

namespace Tests\Feature;

use App\Models\PageVisit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMemberClientSnapshot;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    use CreatesMemberClientSnapshot;
    use RefreshDatabase;

    private function adminUser(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);
        return $user;
    }

    private function memberUser(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

    public function test_admin_route_denied_for_non_admin(): void
    {
        $member = $this->memberUser();

        $this->actingAs($member)
            ->get('/admin/statistiken')
            ->assertStatus(403);
    }

    public function test_page_visit_logged_and_admin_can_view(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->withHeader('User-Agent', 'TestAgent/1.0')
            ->get('/dashboard')
            ->assertOk();

        $this->assertDatabaseHas('page_visits', [
            'user_id' => $admin->id,
            'path' => '/dashboard',
        ]);

        $this->assertDatabaseHas('member_client_snapshots', [
            'user_id' => $admin->id,
            'user_agent' => 'TestAgent/1.0',
        ]);

        $this->actingAs($admin)->get('/admin/statistiken')->assertOk();
    }

    public function test_statistik_charts_render_with_accessible_wrapper(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/admin/statistiken');

        $response->assertOk();
        $response->assertSee('data-chart-wrapper', false);
        $response->assertSee('role="img"', false);
        $response->assertSee('aria-labelledby="homepage-visits-heading"', false);
    }

    public function test_homepage_visits_are_separated_and_routes_are_grouped(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();

        PageVisit::create(['user_id' => $admin->id, 'path' => '/']);
        PageVisit::create(['user_id' => $admin->id, 'path' => '/?ref=promo']);
        PageVisit::create(['user_id' => $member->id, 'path' => '/']);

        PageVisit::create(['user_id' => $admin->id, 'path' => '/rezensionen']);
        PageVisit::create(['user_id' => $member->id, 'path' => '/rezensionen/660']);
        PageVisit::create(['user_id' => $member->id, 'path' => '/rezensionen/660/edit']);

        $response = $this->actingAs($admin)->get('/admin/statistiken');

        $response->assertOk();
        $response->assertViewHas('homepageVisits', 3);

        $response->assertViewHas('visitData', function ($data) {
            $collection = collect($data);
            $rezensionen = $collection->firstWhere('path', '/rezensionen');

            $this->assertNotNull($rezensionen);
            $this->assertSame(3, $rezensionen['total']);
            $this->assertNull($collection->firstWhere('path', '/rezensionen/660'));
            $this->assertFalse($collection->contains(fn ($row) => $row['path'] === '/'));

            return true;
        });

        $response->assertViewHas('userVisitData', function ($data) use ($member) {
            $collection = collect($data);
            $memberEntry = $collection->firstWhere(fn ($row) => $row['path'] === '/rezensionen' && $row['user_id'] === $member->id);

            $this->assertNotNull($memberEntry);
            $this->assertSame(2, $memberEntry['total']);
            $this->assertNull($collection->firstWhere('path', '/rezensionen/660'));

            return true;
        });

        $response->assertViewHas('activityData', function ($data) {
            $this->assertIsArray($data);
            $this->assertArrayHasKey('all', $data);
            $this->assertCount(24, $data['all']);

            return true;
        });

        $response->assertViewHas('activityTimeline', function ($data) {
            $collection = collect($data);

            $this->assertCount(7 * 24, $collection);

            $firstEntry = $collection->first();
            $this->assertIsArray($firstEntry);
            $this->assertArrayHasKey('weekday', $firstEntry);
            $this->assertArrayHasKey('hour', $firstEntry);
            $this->assertArrayHasKey('total', $firstEntry);

            return true;
        });

        $response->assertSee("allOption.selected = true;", false);
        $response->assertSee("updateActiveChart('all');", false);
        $response->assertSee('id="activeUsersWeekdayChart"', false);
        $response->assertSee('aria-describedby="active-users-weekday-description"', false);
    }

    public function test_browser_usage_statistics_visible_for_admins(): void
    {
        $admin = $this->adminUser();
        $member = $this->memberUser();

        $this->createSnapshot($admin->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', now());
        $this->createSnapshot($member->id, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', now());
        $this->createSnapshot($member->id, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', now()->addSecond());

        $response = $this->actingAs($admin)->get('/admin/statistiken');

        $response->assertOk();
        $response->assertSee('Browsernutzung unserer Mitglieder');
        $response->assertSee('Beliebteste Browser');
        $response->assertSee('Browser-Familien');
        $response->assertSee('Endgeräte unserer Mitglieder');
        $response->assertSeeText('Google Chrome');
        $response->assertSeeText('Safari');
        $response->assertSeeText('Chromium');
        $response->assertSeeText('WebKit');
        $response->assertSeeText('Festgerät');
        $response->assertSeeText('Mobilgerät');
        $response->assertDontSee('Noch keine Login-Daten vorhanden.');
    }
}

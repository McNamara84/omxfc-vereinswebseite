<?php

namespace Tests\Feature;

use App\Models\PageVisit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
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
            ->get('/statistiken')
            ->assertStatus(403);
    }

    public function test_page_visit_logged_and_admin_can_view(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/dashboard')->assertOk();

        $this->assertDatabaseHas('page_visits', [
            'user_id' => $admin->id,
            'path' => '/dashboard',
        ]);

        $this->actingAs($admin)->get('/statistiken')->assertOk();
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

        $response = $this->actingAs($admin)->get('/statistiken');

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

        $response->assertSee("allOption.selected = true;", false);
        $response->assertSee("updateActiveChart('all');", false);
    }
}

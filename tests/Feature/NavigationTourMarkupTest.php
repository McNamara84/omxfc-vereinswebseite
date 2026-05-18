<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class NavigationTourMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_navigation_renders_tour_runner_and_navigation_anchors(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="tour-runner-root"', false)
            ->assertSee('data-tour-key="dashboard"', false)
            ->assertSee('data-tour-key="section-community"', false)
            ->assertSee('data-tour-key="profile-menu"', false)
            ->assertSee('data-tour-key="profile-settings"', false)
            ->assertSee('data-tour-key="mobile-menu-toggle"', false);

        $crawler = new Crawler($response->getContent());

        $this->assertCount(0, $crawler->filter('nav summary button'));
    }
}
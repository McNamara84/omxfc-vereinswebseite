<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(int $points = 0): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        if ($points) {
            $user->incrementTeamPoints($points);
        }

        return $user;
    }

    public function test_index_displays_rewards_and_user_points(): void
    {
        $user = $this->actingMember(5);
        $this->actingAs($user);
        $rewards = config('rewards');

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertViewIs('rewards.index');
        $viewRewards = $response->viewData('rewards');
        $this->assertCount(count($rewards), $viewRewards);
        $response->assertViewHas('userPoints', 5);
    }

    public function test_index_calculates_unlocked_percentages(): void
    {
        $team = Team::membersTeam();

        $user1 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user1, ['role' => \App\Enums\Role::Mitglied->value]);
        $user1->incrementTeamPoints(1);

        $user2 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user2, ['role' => \App\Enums\Role::Mitglied->value]);
        $user2->incrementTeamPoints(3);

        $user3 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user3, ['role' => \App\Enums\Role::Mitglied->value]);

        $this->actingAs($user2);

        $rewards = $this->get('/belohnungen')->viewData('rewards');

        $this->assertEquals(50, $rewards[0]['percentage']);
        $this->assertEquals(25, $rewards[1]['percentage']);
        $this->assertEquals(25, $rewards[2]['percentage']);
    }

    public function test_hardcover_reward_visible(): void
    {
        $user = $this->actingMember(40);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Bewertungen der Hardcover');
    }

    public function test_hardcover_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(39);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Bewertungen der Hardcover');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_hardcover_author_reward_visible(): void
    {
        $user = $this->actingMember(41);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Maddrax-Hardcover je Autor:in');
    }

    public function test_hardcover_author_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(40);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Maddrax-Hardcover je Autor:in');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_top20_themes_reward_visible(): void
    {
        $user = $this->actingMember(42);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - TOP20 Maddrax-Themen');
    }

    public function test_top20_themes_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(41);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - TOP20 Maddrax-Themen');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_mission_mars_reward_visible(): void
    {
        $user = $this->actingMember(43);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Bewertungen der Mission Mars-Heftromane');
    }

    public function test_mission_mars_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(42);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Bewertungen der Mission Mars-Heftromane');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_mission_mars_author_reward_visible(): void
    {
        $user = $this->actingMember(44);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Mission Mars-Heftromane je Autor:in');
    }

    public function test_mission_mars_author_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(43);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Mission Mars-Heftromane je Autor:in');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_volk_der_tiefe_reward_visible(): void
    {
        $user = $this->actingMember(45);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Bewertungen der Das Volk der Tiefe-Heftromane');
    }

    public function test_volk_der_tiefe_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(44);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Bewertungen der Das Volk der Tiefe-Heftromane');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }

    public function test_volk_der_tiefe_author_reward_visible(): void
    {
        $user = $this->actingMember(46);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('Statistik - Das Volk der Tiefe-Heftromane je Autor:in');
    }

    public function test_volk_der_tiefe_author_reward_hidden_when_points_insufficient(): void
    {
        $user = $this->actingMember(45);
        $this->actingAs($user);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $rewards = $response->viewData('rewards');
        $reward = collect($rewards)->firstWhere('title', 'Statistik - Das Volk der Tiefe-Heftromane je Autor:in');
        $this->assertNotNull($reward);
        $this->assertEquals(0, $reward['percentage']);
    }
}

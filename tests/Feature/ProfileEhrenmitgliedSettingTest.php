<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class ProfileEhrenmitgliedSettingTest extends TestCase
{
    use RefreshDatabase;

    private function createEhrenmitglied(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Ehrenmitglied']);
        return $user;
    }

    private function createMitglied(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_ehrenmitglied_sees_review_notification_setting(): void
    {
        $user = $this->createEhrenmitglied();
        $this->actingAs($user);

        $response = $this->get('/user/profile');

        $response->assertOk();
        $user = $user->fresh();
        $this->assertTrue($user->hasRole('Ehrenmitglied'));
        $this->assertDatabaseHas('team_user', [
            'user_id' => $user->id,
            'role' => 'Ehrenmitglied',
        ]);
        $response->assertSee('E-Mail bei neuer Rezension erhalten');
    }

    public function test_regular_member_does_not_see_review_notification_setting(): void
    {
        $user = $this->createMitglied();
        $this->actingAs($user);

        $response = $this->get('/user/profile');

        $response->assertOk();
        $response->assertDontSee('E-Mail bei neuer Rezension erhalten');
    }
}

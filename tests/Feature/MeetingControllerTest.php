<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class MeetingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_unknown_meeting_is_forbidden(): void
    {
        $this->actingAs($this->actingMember());

        $this->post('/meetings/redirect', ['meeting' => 'unknown'])
            ->assertForbidden();
    }
}

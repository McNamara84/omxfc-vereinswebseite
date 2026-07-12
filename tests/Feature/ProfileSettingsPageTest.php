<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::from($role)->value]);

        return $user;
    }

    public function test_profile_settings_page_uses_modernized_shell_sections(): void
    {
        $user = $this->createMember();
        $this->actingAs($user);

        $response = $this->get('/user/profile');

        $response->assertOk();
        $response->assertSee('Profil & Einstellungen');
        $response->assertSee('Persönliche Daten');
        $response->assertSee('Serienspezifische Daten');
        $response->assertSee('Browser-Sitzungen');
        $response->assertSee('Öffentliches Profil ansehen');
        $response->assertSee(route('profile.view.self'), false);
    }
}

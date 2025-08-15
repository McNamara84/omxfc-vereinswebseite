<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\AudiobookEpisode;

class HoerbuchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_admin_can_view_create_form(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk()
            ->assertSee('Neue Hörbuchfolge');
    }

    public function test_admin_can_store_episode(): void
    {
        $user = $this->actingMember('Admin');
        $responsible = $this->actingMember();

        $data = [
            'episode_number' => 'F30',
            'title' => 'Test Titel',
            'author' => 'Autor',
            'planned_release_date' => '2025-12-24',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'notes' => 'Bemerkung',
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertRedirect(route('hoerbuecher.create'));
        $this->assertDatabaseHas('audiobook_episodes', [
            'episode_number' => 'F30',
            'title' => 'Test Titel',
            'author' => 'Autor',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'notes' => 'Bemerkung',
        ]);
    }

    public function test_episode_number_must_be_unique(): void
    {
        $user = $this->actingMember('Admin');

        AudiobookEpisode::create([
            'episode_number' => 'F30',
            'title' => 'Vorhandene Folge',
            'author' => 'Autor',
            'planned_release_date' => '2025-12-24',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 10,
            'notes' => null,
        ]);

        $data = [
            'episode_number' => 'F30',
            'title' => 'Duplikat',
            'author' => 'Zweiter Autor',
            'planned_release_date' => '2025-12-24',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 20,
            'notes' => null,
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertSessionHasErrors('episode_number');
        $this->assertEquals(1, AudiobookEpisode::where('episode_number', 'F30')->count());
    }

    public function test_member_cannot_view_create_form(): void
    {
        $user = $this->actingMember('Mitglied');

        $this->actingAs($user)->get(route('hoerbuecher.create'))
            ->assertForbidden();
    }
}

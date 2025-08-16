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
            'planned_release_date' => '12.2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'notes' => 'Bemerkung',
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertRedirect(route('hoerbuecher.index'));
        $this->assertDatabaseHas('audiobook_episodes', [
            'episode_number' => 'F30',
            'title' => 'Test Titel',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
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
            'planned_release_date' => '24.12.2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 10,
            'notes' => null,
        ]);

        $data = [
            'episode_number' => 'F30',
            'title' => 'Duplikat',
            'author' => 'Zweiter Autor',
            'planned_release_date' => '24.12.2025',
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

    public function test_admin_can_view_index_and_episodes(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Erste Folge',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 50,
            'notes' => 'Bemerkung',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Erste Folge')
            ->assertSee($episode->planned_release_date)
            ->assertSee('Bemerkung')
            ->assertSee('50%')
            ->assertSee(route('hoerbuecher.create'));
    }

    public function test_member_cannot_view_index(): void
    {
        $user = $this->actingMember('Mitglied');

        $this->actingAs($user)->get(route('hoerbuecher.index'))
            ->assertForbidden();
    }

    public function test_index_sorts_by_planned_release_date(): void
    {
        $user = $this->actingMember('Admin');

        $e1 = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Späteste',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $e2 = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Monat',
            'author' => 'Autor',
            'planned_release_date' => '05.2024',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $e3 = AudiobookEpisode::create([
            'episode_number' => 'F3',
            'title' => 'Tag',
            'author' => 'Autor',
            'planned_release_date' => '15.03.2024',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $episodes = $response->viewData('episodes');

        $this->assertEquals([
            'F3', 'F2', 'F1',
        ], $episodes->pluck('episode_number')->toArray());
    }

    public function test_admin_can_update_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Alte Folge',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $data = [
            'episode_number' => 'F2',
            'title' => 'Neue Folge',
            'author' => 'Neuer Autor',
            'planned_release_date' => '2025',
            'status' => 'Veröffentlicht',
            'responsible_user_id' => null,
            'progress' => 100,
            'notes' => 'Aktualisiert',
        ];

        $this->actingAs($user)
            ->put(route('hoerbuecher.update', $episode), $data)
            ->assertRedirect(route('hoerbuecher.index'));

        $this->assertDatabaseHas('audiobook_episodes', [
            'id' => $episode->id,
            'title' => 'Neue Folge',
            'author' => 'Neuer Autor',
            'status' => 'Veröffentlicht',
            'planned_release_date' => '2025',
            'progress' => 100,
            'notes' => 'Aktualisiert',
        ]);
    }

    public function test_admin_can_delete_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F3',
            'title' => 'Lösch mich',
            'author' => 'Autor',
            'planned_release_date' => '01.01.2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('hoerbuecher.destroy', $episode))
            ->assertRedirect(route('hoerbuecher.index'));

        $this->assertDatabaseMissing('audiobook_episodes', [
            'id' => $episode->id,
        ]);
    }

    public function test_invalid_planned_release_date_is_rejected(): void
    {
        $user = $this->actingMember('Admin');

        $invalidDates = ['13.2025', '99.9999', '3025', '01.13.2025', '30.02.2025', '31.04.2025', '2025-01-01'];

        foreach ($invalidDates as $date) {
            $data = [
                'episode_number' => 'FX' . $date,
                'title' => 'Titel',
                'author' => 'Autor',
                'planned_release_date' => $date,
                'status' => 'Skript wird erstellt',
                'responsible_user_id' => null,
                'progress' => 0,
                'notes' => null,
            ];

            $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

            $response->assertSessionHasErrors('planned_release_date');
        }
    }

    public function test_member_cannot_delete_episode(): void
    {
        $user = $this->actingMember('Mitglied');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4',
            'title' => 'Gesichert',
            'author' => 'Autor',
            'planned_release_date' => '01.01.2025',
            'status' => 'Skript wird erstellt',
            'responsible_user_id' => null,
            'progress' => 0,
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('hoerbuecher.destroy', $episode))
            ->assertForbidden();

        $this->assertDatabaseHas('audiobook_episodes', [
            'id' => $episode->id,
        ]);
    }

    public function test_admin_sees_eardrax_dashboard_link_in_navigation(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('EARDRAX Dashboard');
    }
}

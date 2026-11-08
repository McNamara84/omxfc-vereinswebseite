<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AudiobookEpisode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait HoerbuchPublicAccessTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createEpisodeWithRoles(): AudiobookEpisode
    {
        $user = User::factory()->create();
        $episode = AudiobookEpisode::create(['episode_number' => 'F1', 'title' => 'Testfolge Öffentlich', 'author' => 'Testautor', 'planned_release_date' => '01.06.2026', 'status' => 'Skripterstellung', 'responsible_user_id' => $user->id, 'progress' => 42, 'notes' => 'Interne Anmerkung', 'roles_total' => 2, 'roles_filled' => 1]);
        $episode->roles()->createMany([['name' => 'Matthew Drax', 'description' => 'Hauptrolle', 'takes' => 5, 'user_id' => $user->id, 'speaker_name' => null, 'contact_email' => 'geheim@example.com', 'speaker_pseudonym' => 'Die Stimme', 'uploaded' => true], ['name' => 'Aruula', 'description' => 'Nebenrolle', 'takes' => 3, 'user_id' => null, 'speaker_name' => null, 'contact_email' => null, 'speaker_pseudonym' => null, 'uploaded' => false]]);

        return $episode;
    }

    private function createEpisodeWithPreviousSpeaker(): array
    {
        $speaker = User::factory()->create(['name' => 'Vorheriger Sprecher']);
        $earlier = AudiobookEpisode::create(['episode_number' => 'F10', 'title' => 'Frühere Folge', 'author' => 'Autor', 'planned_release_date' => '2025', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 100, 'roles_total' => 1, 'roles_filled' => 1, 'notes' => null]);
        $earlier->roles()->create(['name' => 'Matthew Drax', 'takes' => 1, 'user_id' => $speaker->id]);
        $episode = AudiobookEpisode::create(['episode_number' => 'F11', 'title' => 'Aktuelle Folge', 'author' => 'Autor', 'planned_release_date' => '01.06.2026', 'status' => 'Rollenbesetzung', 'responsible_user_id' => null, 'progress' => 20, 'roles_total' => 1, 'roles_filled' => 0, 'notes' => null]);
        $episode->roles()->create(['name' => 'Matthew Drax', 'takes' => 1]);

        return [$episode, $speaker];
    }

    private function createAgFanhoerbuchTeam(User $leader): Team
    {
        $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Fanhörbücher']);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        return $ag;
    }
}

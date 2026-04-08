<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AudiobookEpisode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class HoerbuchPublicAccessTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createEpisodeWithRoles(): AudiobookEpisode
    {
        $user = User::factory()->create();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Testfolge Öffentlich',
            'author' => 'Testautor',
            'planned_release_date' => '01.06.2026',
            'status' => 'Skripterstellung',
            'responsible_user_id' => $user->id,
            'progress' => 42,
            'notes' => 'Interne Anmerkung',
            'roles_total' => 2,
            'roles_filled' => 1,
        ]);

        $episode->roles()->createMany([
            [
                'name' => 'Matthew Drax',
                'description' => 'Hauptrolle',
                'takes' => 5,
                'user_id' => $user->id,
                'speaker_name' => null,
                'contact_email' => 'geheim@example.com',
                'speaker_pseudonym' => 'Die Stimme',
                'uploaded' => true,
            ],
            [
                'name' => 'Aruula',
                'description' => 'Nebenrolle',
                'takes' => 3,
                'user_id' => null,
                'speaker_name' => null,
                'contact_email' => null,
                'speaker_pseudonym' => null,
                'uploaded' => false,
            ],
        ]);

        return $episode;
    }

    private function createEpisodeWithPreviousSpeaker(): array
    {
        $speaker = User::factory()->create(['name' => 'Vorheriger Sprecher']);

        $earlier = AudiobookEpisode::create([
            'episode_number' => 'F10',
            'title' => 'Frühere Folge',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 100,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $earlier->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
            'user_id' => $speaker->id,
        ]);

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F11',
            'title' => 'Aktuelle Folge',
            'author' => 'Autor',
            'planned_release_date' => '01.06.2026',
            'status' => 'Rollenbesetzung',
            'responsible_user_id' => null,
            'progress' => 20,
            'roles_total' => 1,
            'roles_filled' => 0,
            'notes' => null,
        ]);
        $episode->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
        ]);

        return [$episode, $speaker];
    }

    private function createAgFanhoerbuchTeam(User $leader): Team
    {
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Fanhörbücher',
        ]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        return $ag;
    }

    // ─── Gast-Zugriff auf öffentliche Seiten ─────────────────────────

    public function test_guest_can_view_index(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Hörbuchfolgen')
            ->assertSee($episode->title);
    }

    public function test_guest_can_view_episode_detail(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee($episode->title)
            ->assertSee($episode->author)
            ->assertSee($episode->episode_number);
    }

    // ─── Gast sieht KEINE Bearbeitungs-Elemente ─────────────────────

    public function test_guest_does_not_see_create_button_on_index(): void
    {
        $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.create'))
            ->assertDontSee('Neue Folge');
    }

    public function test_guest_does_not_see_edit_delete_buttons_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Bearbeiten')
            ->assertDontSee('Löschen');
    }

    // ─── Gast sieht KEINE bisherigen Sprecher ───────────────────────

    public function test_guest_does_not_see_previous_speaker_hint(): void
    {
        [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Bisheriger Sprecher')
            ->assertDontSee($speaker->name);
    }

    public function test_regular_member_does_not_see_previous_speaker_hint(): void
    {
        [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Bisheriger Sprecher');
    }

    // ─── Eingeloggter Berechtigter sieht bisherige Sprecher ─────────

    public function test_authorized_user_sees_previous_speaker_hint(): void
    {
        [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();

        $this->actingMember('Admin');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: ' . $speaker->name);
    }

    // ─── Gast hat keinen Zugriff auf Management-Routen ──────────────

    public function test_guest_cannot_access_create(): void
    {
        $this->get(route('hoerbuecher.create'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_store_episode(): void
    {
        $this->post(route('hoerbuecher.store'), [
            'episode_number' => 'F99',
            'title' => 'Hacker-Folge',
            'author' => 'Hacker',
            'planned_release_date' => '2026',
            'status' => 'Skripterstellung',
            'progress' => 0,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('audiobook_episodes', ['title' => 'Hacker-Folge']);
    }

    public function test_guest_cannot_access_edit(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.edit', $episode))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_episode(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->put(route('hoerbuecher.update', $episode), [
            'episode_number' => 'F999',
            'title' => 'Manipuliert',
            'author' => 'Hacker',
            'planned_release_date' => '2026',
            'status' => 'Skripterstellung',
            'progress' => 0,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('audiobook_episodes', ['title' => 'Manipuliert']);
    }

    public function test_guest_cannot_delete_episode(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->delete(route('hoerbuecher.destroy', $episode))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('audiobook_episodes', ['id' => $episode->id]);
    }

    public function test_guest_cannot_toggle_role_uploaded(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $role = $episode->roles->first();

        $this->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => true])
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_previous_speaker_endpoint(): void
    {
        $this->get(route('hoerbuecher.previous-speaker', ['name' => 'Matthew Drax']))
            ->assertRedirect(route('login'));
    }

    // ─── Normales Mitglied hat keinen Zugriff auf Management ────────

    public function test_regular_member_cannot_create_episode(): void
    {
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.create'))
            ->assertForbidden();
    }

    public function test_regular_member_cannot_store_episode(): void
    {
        $this->actingMember('Mitglied');

        $this->post(route('hoerbuecher.store'), [
                'episode_number' => 'F99',
                'title' => 'Unberechtigt',
                'author' => 'Autor',
                'planned_release_date' => '2026',
                'status' => 'Skripterstellung',
                'progress' => 0,
            ])->assertForbidden();

        $this->assertDatabaseMissing('audiobook_episodes', ['title' => 'Unberechtigt']);
    }

    public function test_regular_member_cannot_edit_episode(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.edit', $episode))
            ->assertForbidden();
    }

    public function test_regular_member_cannot_delete_episode(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->delete(route('hoerbuecher.destroy', $episode))
            ->assertForbidden();

        $this->assertDatabaseHas('audiobook_episodes', ['id' => $episode->id]);
    }

    // ─── Berechtigte sehen Bearbeitungs-Elemente ────────────────────

    public function test_admin_sees_create_button_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $this->actingMember('Admin');

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee(route('hoerbuecher.create'))
            ->assertSee('Neue Folge');
    }

    public function test_vorstand_sees_create_button_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $this->actingMember('Vorstand');

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Neue Folge');
    }

    public function test_ag_leader_sees_edit_delete_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $leader = $this->actingMember();
        $this->createAgFanhoerbuchTeam($leader);

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Bearbeiten');
    }

    public function test_admin_sees_edit_delete_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Admin');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Bearbeiten');
    }

    // ─── Normales Mitglied sieht KEINE Bearbeitungs-Elemente ────────

    public function test_regular_member_does_not_see_create_button_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.create'))
            ->assertDontSee('Neue Folge');
    }

    public function test_regular_member_does_not_see_edit_delete_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Bearbeiten');
    }

    // ─── SEO: noindex Meta-Tag ──────────────────────────────────────

    public function test_index_has_noindex_meta_tag(): void
    {
        $response = $this->get(route('hoerbuecher.index'));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_show_has_noindex_meta_tag(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $response = $this->get(route('hoerbuecher.show', $episode));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    // ─── Gast sieht inhaltliche Daten der Folge ────────────────────

    public function test_guest_sees_episode_data_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $response = $this->get(route('hoerbuecher.show', $episode));

        $response->assertOk()
            ->assertSee('Testfolge Öffentlich')
            ->assertSee('Testautor')
            ->assertSee('F1')
            ->assertSee('42%')
            ->assertSee('Matthew Drax')
            ->assertSee('Aruula');
    }

    public function test_guest_sees_episode_data_on_index(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $response = $this->get(route('hoerbuecher.index'));

        $response->assertOk()
            ->assertSee('Testfolge Öffentlich')
            ->assertSee('F1')
            ->assertSee('42%');
    }

    // ─── Navigation: Kein Menü-Eintrag für Gäste ────────────────────

    public function test_guest_does_not_see_eardrax_in_navigation(): void
    {
        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee('EARDRAX Dashboard');
    }

    // ─── Gast sieht KEINE internen Daten ────────────────────────────

    public function test_guest_does_not_see_notes_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Interne Anmerkung')
            ->assertDontSee('Anmerkungen');
    }

    public function test_guest_does_not_see_notes_on_index(): void
    {
        $this->createEpisodeWithRoles();

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee('Interne Anmerkung')
            ->assertDontSee('Bemerkungen');
    }

    public function test_regular_member_does_not_see_notes_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee('Interne Anmerkung')
            ->assertDontSee('Bemerkungen');
    }

    public function test_vorstand_sees_notes_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $this->actingMember('Vorstand');

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Bemerkungen')
            ->assertSee('Interne Anmerkung');
    }

    public function test_ag_member_sees_notes_on_index(): void
    {
        $this->createEpisodeWithRoles();
        $leader = $this->actingMember();
        $this->createAgFanhoerbuchTeam($leader);

        $this->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Bemerkungen')
            ->assertSee('Interne Anmerkung');
    }

    public function test_guest_does_not_see_responsible_person_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $episode->load('responsible');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Verantwortlich');
    }

    // ─── Authentifizierter Nutzer sieht interne Daten ───────────────

    public function test_vorstand_sees_notes_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Vorstand');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Anmerkungen')
            ->assertSee('Interne Anmerkung');
    }

    public function test_ag_member_sees_notes_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $leader = $this->actingMember();
        $this->createAgFanhoerbuchTeam($leader);

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Anmerkungen')
            ->assertSee('Interne Anmerkung');
    }

    public function test_regular_member_does_not_see_notes_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Anmerkungen')
            ->assertDontSee('Interne Anmerkung');
    }

    public function test_vorstand_sees_responsible_person_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $episode->load('responsible');
        $this->actingMember('Vorstand');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Verantwortlich')
            ->assertSee($episode->responsible->name);
    }

    public function test_regular_member_does_not_see_responsible_person_on_show(): void
    {
        $episode = $this->createEpisodeWithRoles();
        $this->actingMember('Mitglied');

        $this->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee('Verantwortlich');
    }

    // ─── Bisheriger-Sprecher: Aktuelle Episode wird ausgeschlossen ──

    public function test_previous_speaker_shown_from_earlier_episode(): void
    {
        $speaker = User::factory()->create(['name' => 'Doppelter Sprecher']);

        $earlier = AudiobookEpisode::create([
            'episode_number' => 'F20',
            'title' => 'Frühere Folge',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'progress' => 100,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $earlier->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
            'user_id' => $speaker->id,
        ]);

        $current = AudiobookEpisode::create([
            'episode_number' => 'F21',
            'title' => 'Aktuelle Folge mit gleichem Sprecher',
            'author' => 'Autor',
            'planned_release_date' => '01.06.2026',
            'status' => 'Aufnahmensammlung',
            'progress' => 50,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $current->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
            'user_id' => $speaker->id,
        ]);

        $this->actingMember('Admin');

        // Bisheriger Sprecher stammt aus der früheren Episode (aktuelle wird ausgeschlossen).
        // Da der gleiche Sprecher auch in einer früheren Episode besetzt war, wird der Hinweis angezeigt.
        $this->get(route('hoerbuecher.show', $current))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: Doppelter Sprecher');
    }

    public function test_previous_speaker_not_shown_when_only_in_current_episode(): void
    {
        $speaker = User::factory()->create(['name' => 'Nur-Aktuell Sprecher']);

        $current = AudiobookEpisode::create([
            'episode_number' => 'F22',
            'title' => 'Folge ohne Vorgänger',
            'author' => 'Autor',
            'planned_release_date' => '01.06.2026',
            'status' => 'Aufnahmensammlung',
            'progress' => 50,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $current->roles()->create([
            'name' => 'Neue Rolle',
            'takes' => 1,
            'user_id' => $speaker->id,
        ]);

        $this->actingMember('Admin');

        // Kein bisheriger Sprecher, da die Rolle nur in der aktuellen Episode existiert
        $this->get(route('hoerbuecher.show', $current))
            ->assertOk()
            ->assertDontSee('Bisheriger Sprecher');
    }

    // ─── robots.txt erlaubt Crawling ────────────────────────────────

    public function test_robots_txt_does_not_disallow_hoerbuecher(): void
    {
        $robotsTxt = file_get_contents(public_path('robots.txt'));

        $this->assertStringNotContainsString('Disallow: /hoerbuecher', $robotsTxt);
    }
}

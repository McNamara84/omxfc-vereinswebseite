<?php

namespace Tests\Feature;

use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\Role;

class HoerbuchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
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

    private function actingAgMember(): User
    {
        $leader = $this->actingMember();
        $ag = $this->createAgFanhoerbuchTeam($leader);

        $member = $this->actingMember();
        $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);

        return $member;
    }

    private function actingAgLeader(): User
    {
        $leader = $this->actingMember();
        $this->createAgFanhoerbuchTeam($leader);

        return $leader;
    }

    public function test_admin_can_view_create_form(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk()
            ->assertSee('Neue Hörbuchfolge');
    }

    public function test_edit_view_shows_compact_takes_column_and_checkbox_header(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F99',
            'title' => 'Layout Test',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 10,
            'notes' => null,
            'roles_total' => 1,
            'roles_filled' => 1,
        ]);

        $episode->roles()->create([
            'name' => 'Testrolle',
            'description' => 'Beschreibung',
            'takes' => 3,
            'speaker_name' => 'Test Sprecher',
            'contact_email' => null,
            'speaker_pseudonym' => null,
            'uploaded' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.edit', $episode));

        $response->assertOk();
        $response->assertSee('id="roles-uploaded-header"', false);
        $response->assertSee('aria-labelledby="roles-uploaded-header"', false);
        $response->assertSee('md:max-w-[6rem]', false);
        $response->assertSee('max="999"', false);
        $this->assertStringNotContainsString('<span>Aufnahme hochgeladen</span></label>', $response->getContent());
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
            'status' => 'Skripterstellung',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'roles' => [
                [
                    'name' => 'R1',
                    'description' => 'Desc1',
                    'takes' => 3,
                    'member_name' => 'Extern',
                    'contact_email' => 'sprecher@example.com',
                    'speaker_pseudonym' => 'Die Stimme',
                    'uploaded' => '1',
                ],
                [
                    'name' => 'R2',
                    'description' => 'Desc2',
                    'takes' => 2,
                    'member_id' => $responsible->id,
                    'contact_email' => null,
                    'speaker_pseudonym' => null,
                    'uploaded' => '0',
                ],
            ],
            'notes' => 'Bemerkung',
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertRedirect(route('hoerbuecher.index'));
        $this->assertDatabaseHas('audiobook_episodes', [
            'episode_number' => 'F30',
            'title' => 'Test Titel',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'roles_total' => 2,
            'roles_filled' => 2,
            'notes' => 'Bemerkung',
        ]);

        $this->assertDatabaseCount('audiobook_roles', 2);
        $episodeId = AudiobookEpisode::where('episode_number', 'F30')->value('id');
        $this->assertDatabaseHas('audiobook_roles', [
            'episode_id' => $episodeId,
            'name' => 'R1',
            'contact_email' => 'sprecher@example.com',
            'speaker_pseudonym' => 'Die Stimme',
            'uploaded' => true,
        ]);
        $this->assertDatabaseHas('audiobook_roles', [
            'episode_id' => $episodeId,
            'name' => 'R2',
            'contact_email' => null,
            'speaker_pseudonym' => null,
            'uploaded' => false,
        ]);
    }

    public function test_contact_and_pseudonym_are_hidden_in_detail_view(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F32',
            'title' => 'Geheime Folge',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 10,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Geheimrolle',
            'description' => 'Top secret',
            'takes' => 1,
            'speaker_name' => 'Öffentlicher Name',
            'contact_email' => 'geheim@example.com',
            'speaker_pseudonym' => 'Verborgene Stimme',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Öffentlicher Name')
            ->assertDontSee('geheim@example.com')
            ->assertDontSee('Verborgene Stimme');
    }

    public function test_detail_view_hides_upload_column_and_controls(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F42',
            'title' => 'Keine Upload Checkbox',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 10,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Erzähler',
            'description' => 'Erzählt die Geschichte',
            'takes' => 4,
            'uploaded' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.show', $episode));

        $response->assertOk();
        $response->assertSee('Sprecher');
        $response->assertDontSee('id="roles-uploaded-header"', false);
        $response->assertDontSee('uploaded-role-');
        $response->assertDontSee('data-auto-submit');
        $response->assertSee('Upload vorhanden');
        $response->assertSee('bg-green-100', false);
    }

    public function test_vorstand_can_store_episode(): void
    {
        $user = $this->actingMember('Vorstand');
        $responsible = $this->actingMember();

        $data = [
            'episode_number' => 'F31',
            'title' => 'Vorstand Folge',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => $responsible->id,
            'progress' => 50,
            'roles' => [],
            'notes' => null,
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertRedirect(route('hoerbuecher.index'));
        $this->assertDatabaseHas('audiobook_episodes', [
            'episode_number' => 'F31',
            'title' => 'Vorstand Folge',
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
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 10,
            'roles_total' => 10,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $data = [
            'episode_number' => 'F30',
            'title' => 'Duplikat',
            'author' => 'Zweiter Autor',
            'planned_release_date' => '24.12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 20,
            'roles' => [],
            'notes' => null,
        ];

        $response = $this->actingAs($user)->post(route('hoerbuecher.store'), $data);

        $response->assertSessionHasErrors('episode_number');
        $this->assertEquals(1, AudiobookEpisode::where('episode_number', 'F30')->count());
    }

    public function test_store_validation_errors(): void
    {
        $user = $this->actingMember('Admin');

        $response = $this->actingAs($user)
            ->from(route('hoerbuecher.create'))
            ->post(route('hoerbuecher.store'), []);

        $response->assertRedirect(route('hoerbuecher.create', [], false));
        $response->assertSessionHasErrors(['episode_number', 'title', 'author', 'planned_release_date', 'status', 'progress']);
    }

    public function test_contact_email_must_be_valid(): void
    {
        $user = $this->actingMember('Admin');

        $data = [
            'episode_number' => 'F40',
            'title' => 'Validierung',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 10,
            'roles' => [
                [
                    'name' => 'Test',
                    'description' => 'Test',
                    'takes' => 1,
                    'contact_email' => 'keine-mail',
                ],
            ],
            'notes' => null,
        ];

        $response = $this->actingAs($user)
            ->from(route('hoerbuecher.create'))
            ->post(route('hoerbuecher.store'), $data);

        $response->assertRedirect(route('hoerbuecher.create', [], false));
        $response->assertSessionHasErrors(['roles.0.contact_email']);
    }

    public function test_member_cannot_view_create_form(): void
    {
        $user = $this->actingMember('Mitglied');

        $this->actingAs($user)->get(route('hoerbuecher.create'))
            ->assertForbidden();
    }

    public function test_vorstand_can_view_create_form(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk()
            ->assertSee('Neue Hörbuchfolge');
    }

    public function test_admin_can_view_index_and_episodes(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Erste Folge',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 50,
            'roles_total' => 10,
            'roles_filled' => 5,
            'notes' => 'Bemerkung',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertSee('Erste Folge')
            ->assertSee($episode->planned_release_date)
            ->assertSee('Bemerkung')
            ->assertSee('50%')
            ->assertSee('5/10')
            ->assertSee(route('hoerbuecher.create'))
            ->assertSee('data-href="'.route('hoerbuecher.show', $episode).'"', false)
            ->assertSee('role="button"', false)
            ->assertSee('tabindex="0"', false)
            ->assertDontSee('onclick="window.location', false)
            ->assertDontSee('onkeydown', false);
    }

    public function test_index_displays_role_filter_with_distinct_names(): void
    {
        $user = $this->actingMember('Admin');

        $firstEpisode = AudiobookEpisode::create([
            'episode_number' => 'F10',
            'title' => 'Erzählung Eins',
            'author' => 'Autorin',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 20,
            'roles_total' => 2,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $secondEpisode = AudiobookEpisode::create([
            'episode_number' => 'F11',
            'title' => 'Erzählung Zwei',
            'author' => 'Autor',
            'planned_release_date' => '2026',
            'status' => 'Aufnahmensammlung',
            'responsible_user_id' => null,
            'progress' => 40,
            'roles_total' => 3,
            'roles_filled' => 2,
            'notes' => null,
        ]);

        $firstEpisode->roles()->createMany([
            ['name' => 'Protagonist'],
            ['name' => 'Erzählerin'],
        ]);

        $secondEpisode->roles()->createMany([
            ['name' => 'Antagonist'],
            ['name' => 'Protagonist'],
            ['name' => 'Gastauftritt'],
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $response->assertOk();
        $response->assertSee('id="role-name-filter"', false);
        $response->assertSee('aria-label="Hörbuchfolgen nach Rolle filtern"', false);

        $content = $response->getContent();
        $this->assertStringContainsString('["Protagonist","Erz\\u00e4hlerin"]', $content);
        $this->assertStringContainsString('["Antagonist","Protagonist","Gastauftritt"]', $content);

        $this->assertSame(1, substr_count($content, 'value="Protagonist"'));
        $this->assertSame(1, substr_count($content, 'value="Antagonist"'));
        $this->assertSame(1, substr_count($content, 'value="Erzählerin"'));
        $this->assertSame(1, substr_count($content, 'value="Gastauftritt"'));
    }

    public function test_admin_can_view_episode_details(): void
    {
        $user = $this->actingMember('Admin');
        $responsible = $this->actingMember();
        $speaker = $this->actingMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F9',
            'title' => 'Detailfolge',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => $responsible->id,
            'progress' => 40,
            'roles_total' => 2,
            'roles_filled' => 2,
            'notes' => 'Notiz',
        ]);

        $episode->roles()->create([
            'name' => 'R1',
            'description' => 'Desc1',
            'takes' => 3,
            'user_id' => $speaker->id,
        ]);
        $episode->roles()->create([
            'name' => 'R2',
            'description' => 'Desc2',
            'takes' => 1,
            'speaker_name' => 'Extern',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Detailfolge')
            ->assertSee($responsible->name)
            ->assertSee('Notiz')
            ->assertSee('2/2')
            ->assertSee('R1')
            ->assertSee('Desc1')
            ->assertSee($speaker->name)
            ->assertSee('R2')
            ->assertSee('Desc2')
            ->assertSee('Extern')
            ->assertSee(route('hoerbuecher.edit', $episode));
    }

    public function test_episode_show_displays_previous_speaker_hint(): void
    {
        $user = $this->actingMember('Admin');
        $actor = $this->actingMember();

        $earlier = AudiobookEpisode::create([
            'episode_number' => 'F29',
            'title' => 'Frühere',
            'author' => 'Autor',
            'planned_release_date' => '2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $earlier->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
            'user_id' => $actor->id,
        ]);

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F30',
            'title' => 'Aktuelle',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 0,
            'notes' => null,
        ]);
        $episode->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: '.$actor->name);
    }

    public function test_edit_form_displays_previous_speaker_hint(): void
    {
        $user = $this->actingMember('Admin');
        $actor = $this->actingMember();

        $earlier = AudiobookEpisode::create([
            'episode_number' => 'F29',
            'title' => 'Frühere',
            'author' => 'Autor',
            'planned_release_date' => '2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        $earlier->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
            'user_id' => $actor->id,
        ]);

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F30',
            'title' => 'Aktuelle',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 0,
            'notes' => null,
        ]);
        $episode->roles()->create([
            'name' => 'Matthew Drax',
            'takes' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.edit', $episode))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: '.$actor->name);
    }

    public function test_edit_form_displays_contact_and_pseudonym_fields(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F33',
            'title' => 'Bearbeitung',
            'author' => 'Autor',
            'planned_release_date' => '2026',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Rolle',
            'takes' => 1,
            'contact_email' => 'sichtbar@example.net',
            'speaker_pseudonym' => 'Alias X',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.edit', $episode))
            ->assertOk()
            ->assertSee('value="sichtbar@example.net"', false)
            ->assertSee('value="Alias X"', false);
    }

    public function test_notes_are_sanitized_and_escaped_in_views(): void
    {
        $user = $this->actingMember('Admin');
        $malicious = '<script>alert("xss")</script>';
        $sanitized = 'alert("xss")';

        $this->actingAs($user)->post(route('hoerbuecher.store'), [
            'episode_number' => 'F5',
            'title' => 'XSS Test',
            'author' => 'Autor',
            'planned_release_date' => '2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles' => [],
            'notes' => $malicious,
        ]);

        $episode = AudiobookEpisode::first();

        $this->assertDatabaseHas('audiobook_episodes', [
            'id' => $episode->id,
            'notes' => $sanitized,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertDontSee($malicious, false)
            ->assertSee($sanitized);

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertDontSee($malicious, false)
            ->assertSee($sanitized);
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
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $e2 = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Monat',
            'author' => 'Autor',
            'planned_release_date' => '05.2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $e3 = AudiobookEpisode::create([
            'episode_number' => 'F3',
            'title' => 'Tag',
            'author' => 'Autor',
            'planned_release_date' => '15.03.2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $episodes = $response->viewData('episodes');

        $this->assertEquals([
            'F3', 'F2', 'F1',
        ], $episodes->pluck('episode_number')->toArray());
    }

    public function test_index_displays_statistics_cards(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');

        $user = $this->actingMember('Admin');

        AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Erste',
            'author' => 'Autor',
            'planned_release_date' => '02.01.2025',
            'status' => 'Rollenbesetzung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 5,
            'roles_filled' => 2,
            'notes' => null,
        ]);

        AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Zweite',
            'author' => 'Autor',
            'planned_release_date' => '05.01.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 3,
            'roles_filled' => 3,
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertSee('data-unfilled-roles="3"', false)
            ->assertSee('data-open-episodes="1"', false)
            ->assertSee('data-days-left="1"', false)
            ->assertSee('Tage bis Erste veröffentlicht wird (02.01.2025)', false);
    }

    public function test_admin_can_update_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Alte Folge',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 10,
            'roles_filled' => 5,
            'notes' => null,
        ]);

        $data = [
            'episode_number' => 'F2',
            'title' => 'Neue Folge',
            'author' => 'Neuer Autor',
            'planned_release_date' => '2025',
            'status' => 'Veröffentlichung',
            'responsible_user_id' => null,
            'progress' => 100,
            'roles' => [
                [
                    'name' => 'Neue Rolle',
                    'description' => 'desc',
                    'takes' => 1,
                    'member_name' => 'Extern',
                    'contact_email' => 'rolle@example.org',
                    'speaker_pseudonym' => 'Shadow Voice',
                    'uploaded' => '1',
                ],
            ],
            'notes' => 'Aktualisiert',
        ];

        $this->actingAs($user)
            ->put(route('hoerbuecher.update', $episode), $data)
            ->assertRedirect(route('hoerbuecher.index'));

        $this->assertDatabaseHas('audiobook_episodes', [
            'id' => $episode->id,
            'title' => 'Neue Folge',
            'author' => 'Neuer Autor',
            'status' => 'Veröffentlichung',
            'planned_release_date' => '2025',
            'progress' => 100,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => 'Aktualisiert',
        ]);

        $this->assertDatabaseHas('audiobook_roles', [
            'episode_id' => $episode->id,
            'name' => 'Neue Rolle',
            'contact_email' => 'rolle@example.org',
            'speaker_pseudonym' => 'Shadow Voice',
            'uploaded' => true,
        ]);
    }

    public function test_update_validation_errors(): void
    {
        $user = $this->actingMember('Admin');
        $episode = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Alt',
            'author' => 'Autor',
            'planned_release_date' => '12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)
            ->from(route('hoerbuecher.edit', $episode))
            ->put(route('hoerbuecher.update', $episode), [
                'episode_number' => '',
                'title' => '',
                'author' => '',
                'planned_release_date' => '',
                'status' => '',
                'progress' => 200,
            ]);

        $response->assertRedirect(route('hoerbuecher.edit', $episode, false));
        $response->assertSessionHasErrors(['episode_number', 'title', 'author', 'planned_release_date', 'status', 'progress']);
    }

    public function test_admin_can_delete_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F3',
            'title' => 'Lösch mich',
            'author' => 'Autor',
            'planned_release_date' => '01.01.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
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
                'episode_number' => 'FX'.$date,
                'title' => 'Titel',
                'author' => 'Autor',
                'planned_release_date' => $date,
                'status' => 'Skripterstellung',
                'responsible_user_id' => null,
                'progress' => 0,
                'roles' => [],
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
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
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

    public function test_vorstand_sees_eardrax_dashboard_link_in_navigation(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('EARDRAX Dashboard');
    }

    public function test_kassenwart_sees_eardrax_dashboard_link_in_navigation(): void
    {
        $user = $this->actingMember('Kassenwart');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('EARDRAX Dashboard');
    }

    public function test_ag_member_sees_eardrax_dashboard_link_in_navigation(): void
    {
        $user = $this->actingAgMember();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('EARDRAX Dashboard');
    }

    public function test_member_does_not_see_eardrax_dashboard_link_in_navigation(): void
    {
        $user = $this->actingMember('Mitglied');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertDontSee('EARDRAX Dashboard');
    }

    public function test_vorstand_can_view_index(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)->get(route('hoerbuecher.index'))
            ->assertOk();
    }

    public function test_kassenwart_can_view_index(): void
    {
        $user = $this->actingMember('Kassenwart');

        $this->actingAs($user)->get(route('hoerbuecher.index'))
            ->assertOk();
    }

    public function test_ag_member_can_view_index_and_episode_but_not_edit(): void
    {
        $user = $this->actingAgMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Testfolge',
            'author' => 'Autor',
            'planned_release_date' => '01.01.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.create'));

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.edit', $episode));

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('hoerbuecher.edit', $episode))
            ->assertForbidden();
    }

    public function test_ag_leader_can_view_create_form(): void
    {
        $user = $this->actingAgLeader();

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk();
    }

    public function test_ag_leader_can_toggle_role_uploaded(): void
    {
        $leader = $this->actingAgLeader();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F99',
            'title' => 'Upload Test',
            'author' => 'Autor',
            'planned_release_date' => '01.01.2026',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $role = AudiobookRole::create([
            'episode_id' => $episode->id,
            'name' => 'Testrolle',
            'description' => null,
            'takes' => 0,
            'user_id' => null,
            'speaker_name' => 'Speaker',
            'uploaded' => false,
        ]);

        $this->actingAs($leader)
            ->from(route('hoerbuecher.show', $episode))
            ->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => true])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->uploaded);

        $this->actingAs($leader)
            ->from(route('hoerbuecher.show', $episode))
            ->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => false])
            ->assertRedirect();

        $this->assertFalse($role->fresh()->uploaded);
    }

    public function test_ag_member_cannot_toggle_role_uploaded(): void
    {
        $member = $this->actingAgMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F98',
            'title' => 'Upload Forbidden',
            'author' => 'Autor',
            'planned_release_date' => '01.02.2026',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $role = AudiobookRole::create([
            'episode_id' => $episode->id,
            'name' => 'Verboten',
            'description' => null,
            'takes' => 0,
            'user_id' => null,
            'speaker_name' => 'Speaker',
            'uploaded' => false,
        ]);

        $this->actingAs($member)
            ->from(route('hoerbuecher.show', $episode))
            ->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => true])
            ->assertForbidden();

        $this->assertFalse($role->fresh()->uploaded);
    }

    public function test_previous_speaker_endpoint_returns_last_assigned_member(): void
    {
        $admin = $this->actingMember('Admin');
        $speaker1 = $this->actingMember();
        $speaker2 = $this->actingMember();

        $episode1 = AudiobookEpisode::create([
            'episode_number' => 'F29',
            'title' => 'Ep1',
            'author' => 'Autor',
            'planned_release_date' => '24.12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        AudiobookRole::create([
            'episode_id' => $episode1->id,
            'name' => 'Matthew Drax',
            'takes' => 0,
            'user_id' => $speaker1->id,
        ]);

        $episode2 = AudiobookEpisode::create([
            'episode_number' => 'F30',
            'title' => 'Ep2',
            'author' => 'Autor',
            'planned_release_date' => '25.12.2025',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 1,
            'roles_filled' => 1,
            'notes' => null,
        ]);
        AudiobookRole::create([
            'episode_id' => $episode2->id,
            'name' => 'Matthew Drax',
            'takes' => 0,
            'user_id' => $speaker2->id,
        ]);

        $this->actingAs($admin)
            ->get(route('hoerbuecher.previous-speaker', ['name' => 'Matthew Drax']))
            ->assertJson(['speaker' => $speaker2->name]);
    }

    public function test_previous_speaker_endpoint_validates_name(): void
    {
        $admin = $this->actingMember('Admin');
        $longName = str_repeat('a', 300);

        $this->actingAs($admin)
            ->getJson(route('hoerbuecher.previous-speaker', ['name' => $longName]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_previous_speaker_endpoint_requires_name(): void
    {
        $admin = $this->actingMember('Admin');

        $this->actingAs($admin)
            ->getJson(route('hoerbuecher.previous-speaker'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

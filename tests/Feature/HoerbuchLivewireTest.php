<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\HoerbuchForm;
use App\Livewire\HoerbuchIndex;
use App\Livewire\HoerbuchShow;
use App\Models\AudiobookEpisode;
use App\Models\AudiobookRole;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Large;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[Large]
class HoerbuchLivewireTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

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

    // ── Index Tests ──────────────────────────────────────────────

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
            ->assertSee('id="episode-select-filters"', false)
            ->assertSee('<fieldset', false)
            ->assertSee('id="episode-checkbox-filters"', false)
            ->assertSee('<legend class="text-sm font-semibold text-base-content w-full mb-2">Checkbox-Filter</legend>', false)
            ->assertSee('href="'.route('hoerbuecher.show', $episode).'"', false)
            ->assertSee('wire:navigate', false)
            ->assertSee('Unveröffentlicht')
            ->assertSee('Unveröffentlichte Folgen werden angezeigt, solange der Filter aktiv ist. Deaktiviere den Filter, um bereits veröffentlichte Folgen einzublenden.', false)
            ->assertDontSee('onclick="window.location', false)
            ->assertDontSee('onkeydown', false)
            ->assertDontSee('role="button"', false)
            ->assertDontSee('tabindex="0"', false);
    }

    public function test_member_can_view_index(): void
    {
        $user = $this->actingMember('Mitglied');

        $this->actingAs($user)->get(route('hoerbuecher.index'))
            ->assertOk();
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

    public function test_index_displays_role_filter_with_distinct_names(): void
    {
        $user = $this->actingMember('Admin');

        $firstEpisode = AudiobookEpisode::create([
            'episode_number' => 'F10', 'title' => 'Erzählung Eins', 'author' => 'Autorin',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 20,
            'roles_total' => 2, 'roles_filled' => 1, 'notes' => null,
        ]);

        $secondEpisode = AudiobookEpisode::create([
            'episode_number' => 'F11', 'title' => 'Erzählung Zwei', 'author' => 'Autor',
            'planned_release_date' => '2026', 'status' => 'Aufnahmensammlung',
            'responsible_user_id' => null, 'progress' => 40,
            'roles_total' => 3, 'roles_filled' => 2, 'notes' => null,
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

        $crawler = new Crawler($response->getContent());

        $firstRowRoles = $crawler
            ->filter("tr[data-episode-id='{$firstEpisode->id}']")
            ->attr('data-role-names');
        $secondRowRoles = $crawler
            ->filter("tr[data-episode-id='{$secondEpisode->id}']")
            ->attr('data-role-names');

        $this->assertSame(
            ['Protagonist', 'Erzählerin'],
            json_decode($firstRowRoles, true, 512, JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            ['Antagonist', 'Protagonist', 'Gastauftritt'],
            json_decode($secondRowRoles, true, 512, JSON_THROW_ON_ERROR)
        );

        $roleOptions = $crawler
            ->filter('#role-name-filter option')
            ->each(fn ($option) => $option->attr('value'));

        $this->assertContains('', $roleOptions);
        $this->assertContains('Protagonist', $roleOptions);
        $this->assertContains('Antagonist', $roleOptions);
        $this->assertContains('Erzählerin', $roleOptions);
        $this->assertContains('Gastauftritt', $roleOptions);
        $this->assertSame(
            count($roleOptions) - 1,
            count(array_unique(array_filter($roleOptions)))
        );
    }

    public function test_index_uses_compact_role_filter_labels(): void
    {
        $user = $this->actingMember('Admin');

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $response->assertOk();
        $response->assertSee('Besetzt');
        $response->assertSee('Unbesetzt');
        $response->assertDontSee('Rollen besetzt');
        $response->assertDontSee('Rollen unbesetzt');
    }

    public function test_index_sorts_by_planned_release_date(): void
    {
        $user = $this->actingMember('Admin');

        AudiobookEpisode::create([
            'episode_number' => 'F1', 'title' => 'Späteste', 'author' => 'Autor',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        AudiobookEpisode::create([
            'episode_number' => 'F2', 'title' => 'Monat', 'author' => 'Autor',
            'planned_release_date' => '05.2024', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        AudiobookEpisode::create([
            'episode_number' => 'F3', 'title' => 'Tag', 'author' => 'Autor',
            'planned_release_date' => '15.03.2024', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));
        $crawler = new Crawler($response->getContent());
        $episodeNumbers = $crawler->filter('table tbody tr td:first-child')->each(fn ($td) => trim($td->text()));

        $this->assertSame(['F3', 'F2', 'F1'], $episodeNumbers);
    }

    public function test_index_displays_statistics_cards(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');

        $user = $this->actingMember('Admin');

        $episodeWithOpenRoles = AudiobookEpisode::create([
            'episode_number' => 'F1', 'title' => 'Erste', 'author' => 'Autor',
            'planned_release_date' => '02.01.2025', 'status' => 'Rollenbesetzung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        $episodeWithOpenRoles->roles()->createMany([
            ['name' => 'Alpha'],
            ['name' => 'Beta'],
            ['name' => 'Gamma'],
            ['name' => 'Delta', 'speaker_name' => 'Sprecherin Delta'],
            ['name' => 'Epsilon', 'speaker_name' => 'Sprecher Epsilon'],
        ]);

        $episodeWithoutOpenRoles = AudiobookEpisode::create([
            'episode_number' => 'F2', 'title' => 'Zweite', 'author' => 'Autor',
            'planned_release_date' => '05.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        $episodeWithoutOpenRoles->roles()->createMany([
            ['name' => 'Zeta', 'speaker_name' => 'Sprecher Zeta'],
            ['name' => 'Eta', 'speaker_name' => 'Sprecher Eta'],
            ['name' => 'Theta', 'speaker_name' => 'Sprecher Theta'],
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));

        $response
            ->assertSee('data-unfilled-roles="3"', false)
            ->assertSee('data-open-episodes="1"', false)
            ->assertSee('data-days-left="1"', false)
            ->assertSee('Tage bis Erste veröffentlicht wird (02.01.2025)', false);
    }

    public function test_index_counts_unique_unfilled_role_names(): void
    {
        Carbon::setTestNow(null);

        $user = $this->actingMember('Admin');

        $firstEpisode = AudiobookEpisode::create([
            'episode_number' => 'F10', 'title' => 'Doppelte Rolle', 'author' => 'Autorin',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 0, 'notes' => null,
        ]);

        $secondEpisode = AudiobookEpisode::create([
            'episode_number' => 'F11', 'title' => 'Noch mehr Rollen', 'author' => 'Autor',
            'planned_release_date' => '2026', 'status' => 'Rollenbesetzung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 2, 'roles_filled' => 0, 'notes' => null,
        ]);

        $thirdEpisode = AudiobookEpisode::create([
            'episode_number' => 'F12', 'title' => 'Besetzte Rollen', 'author' => 'Autor',
            'planned_release_date' => '2024', 'status' => 'Audiobearbeitung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $firstEpisode->roles()->create(['name' => '  Alex  ']);
        $secondEpisode->roles()->createMany([
            ['name' => 'alex'],
            ['name' => 'CHRIS'],
        ]);
        $thirdEpisode->roles()->create([
            'name' => 'Chris',
            'speaker_name' => 'Bereits Besetzt',
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.index'));
        $response->assertSee('data-unfilled-roles="2"', false);
    }

    // ── Show Tests ───────────────────────────────────────────────

    public function test_admin_can_view_episode_details(): void
    {
        $user = $this->actingMember('Admin');
        $responsible = $this->actingMember();
        $speaker = $this->actingMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F9', 'title' => 'Detailfolge', 'author' => 'Autor',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => $responsible->id, 'progress' => 40,
            'roles_total' => 2, 'roles_filled' => 2, 'notes' => 'Notiz',
        ]);

        $episode->roles()->create([
            'name' => 'R1', 'description' => 'Desc1', 'takes' => 3,
            'user_id' => $speaker->id,
        ]);
        $episode->roles()->create([
            'name' => 'R2', 'description' => 'Desc2', 'takes' => 1,
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
            'episode_number' => 'F29', 'title' => 'Frühere', 'author' => 'Autor',
            'planned_release_date' => '2024', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);
        $earlier->roles()->create(['name' => 'Matthew Drax', 'takes' => 1, 'user_id' => $actor->id]);

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F30', 'title' => 'Aktuelle', 'author' => 'Autor',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 0, 'notes' => null,
        ]);
        $episode->roles()->create(['name' => 'Matthew Drax', 'takes' => 1]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: '.$actor->name);
    }

    public function test_contact_and_pseudonym_are_hidden_in_detail_view(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F32', 'title' => 'Geheime Folge', 'author' => 'Autor',
            'planned_release_date' => '12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 10,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Geheimrolle', 'description' => 'Top secret', 'takes' => 1,
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
            'episode_number' => 'F42', 'title' => 'Keine Upload Checkbox', 'author' => 'Autor',
            'planned_release_date' => '12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 10,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Erzähler', 'description' => 'Erzählt die Geschichte',
            'takes' => 4, 'uploaded' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.show', $episode));

        $response->assertOk();
        $response->assertSee('Sprecher');
        $response->assertDontSee('id="roles-uploaded-header"', false);
        $response->assertDontSee('uploaded-role-');
        $response->assertDontSee('data-auto-submit');
        $response->assertSee('Upload vorhanden');
        $response->assertSee('bg-success/10', false);
    }

    public function test_admin_can_delete_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F3', 'title' => 'Lösch mich', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchShow::class, ['episode' => $episode])
            ->call('deleteEpisode')
            ->assertRedirect(route('hoerbuecher.index'));

        $this->assertDatabaseMissing('audiobook_episodes', ['id' => $episode->id]);
    }

    public function test_member_cannot_delete_episode(): void
    {
        $user = $this->actingMember('Mitglied');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4', 'title' => 'Gesichert', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchShow::class, ['episode' => $episode])
            ->call('deleteEpisode')
            ->assertForbidden();

        $this->assertDatabaseHas('audiobook_episodes', ['id' => $episode->id]);
    }

    public function test_guest_cannot_delete_episode(): void
    {
        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4G', 'title' => 'Gast verboten', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::test(HoerbuchShow::class, ['episode' => $episode])
            ->call('deleteEpisode')
            ->assertForbidden();

        $this->assertDatabaseHas('audiobook_episodes', ['id' => $episode->id]);
    }

    public function test_ag_member_cannot_delete_episode(): void
    {
        $member = $this->actingAgMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4M', 'title' => 'AG-Mitglied verboten', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($member)
            ->test(HoerbuchShow::class, ['episode' => $episode])
            ->call('deleteEpisode')
            ->assertForbidden();

        $this->assertDatabaseHas('audiobook_episodes', ['id' => $episode->id]);
    }

    // ── Create / Store Tests ─────────────────────────────────────

    public function test_admin_can_view_create_form(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk()
            ->assertSee('Neue Hörbuchfolge');
    }

    public function test_vorstand_can_view_create_form(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)
            ->get(route('hoerbuecher.create'))
            ->assertOk()
            ->assertSee('Neue Hörbuchfolge');
    }

    public function test_member_cannot_view_create_form(): void
    {
        $user = $this->actingMember('Mitglied');

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->assertForbidden();
    }

    public function test_guest_cannot_open_create_form(): void
    {
        Livewire::test(HoerbuchForm::class)
            ->assertForbidden();
    }

    public function test_member_cannot_open_edit_form(): void
    {
        $user = $this->actingMember('Mitglied');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4E', 'title' => 'Edit verboten', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class, ['episode' => $episode])
            ->assertForbidden();
    }

    public function test_guest_cannot_open_edit_form(): void
    {
        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4EG', 'title' => 'Edit Gast verboten', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::test(HoerbuchForm::class, ['episode' => $episode])
            ->assertForbidden();
    }

    public function test_ag_member_cannot_open_edit_form(): void
    {
        $member = $this->actingAgMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F4EAM', 'title' => 'Edit AG-Mitglied verboten', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($member)
            ->test(HoerbuchForm::class, ['episode' => $episode])
            ->assertForbidden();
    }

    public function test_admin_can_store_episode(): void
    {
        $user = $this->actingMember('Admin');
        $responsible = $this->actingMember();

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', 'F30')
            ->set('title', 'Test Titel')
            ->set('author', 'Autor')
            ->set('planned_release_date', '12.2025')
            ->set('status', 'Skripterstellung')
            ->set('responsible_user_id', $responsible->id)
            ->set('progress', 50)
            ->set('notes', 'Bemerkung')
            ->set('roles', [
                [
                    'name' => 'R1', 'description' => 'Desc1', 'takes' => 3,
                    'member_name' => 'Extern', 'member_id' => '',
                    'contact_email' => 'sprecher@example.com',
                    'speaker_pseudonym' => 'Die Stimme',
                    'uploaded' => true,
                ],
                [
                    'name' => 'R2', 'description' => 'Desc2', 'takes' => 2,
                    'member_id' => $responsible->id, 'member_name' => $responsible->name,
                    'contact_email' => '', 'speaker_pseudonym' => '',
                    'uploaded' => false,
                ],
            ])
            ->call('save')
            ->assertRedirect(route('hoerbuecher.index'));

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
            'contact_email' => '',
            'speaker_pseudonym' => '',
            'uploaded' => false,
        ]);
    }

    public function test_vorstand_can_store_episode(): void
    {
        $user = $this->actingMember('Vorstand');

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', 'F31')
            ->set('title', 'Vorstand Folge')
            ->set('author', 'Autor')
            ->set('planned_release_date', '12.2025')
            ->set('status', 'Skripterstellung')
            ->set('progress', 50)
            ->call('save')
            ->assertRedirect(route('hoerbuecher.index'));

        $this->assertDatabaseHas('audiobook_episodes', [
            'episode_number' => 'F31',
            'title' => 'Vorstand Folge',
        ]);
    }

    public function test_episode_number_must_be_unique(): void
    {
        $user = $this->actingMember('Admin');

        AudiobookEpisode::create([
            'episode_number' => 'F30', 'title' => 'Vorhandene Folge', 'author' => 'Autor',
            'planned_release_date' => '24.12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 10,
            'roles_total' => 10, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', 'F30')
            ->set('title', 'Duplikat')
            ->set('author', 'Zweiter Autor')
            ->set('planned_release_date', '24.12.2025')
            ->set('status', 'Skripterstellung')
            ->set('progress', 20)
            ->call('save')
            ->assertHasErrors('episode_number');

        $this->assertEquals(1, AudiobookEpisode::where('episode_number', 'F30')->count());
    }

    public function test_store_validation_errors(): void
    {
        $user = $this->actingMember('Admin');

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', '')
            ->set('title', '')
            ->set('author', '')
            ->set('planned_release_date', '')
            ->set('status', '')
            ->set('progress', 0)
            ->call('save')
            ->assertHasErrors(['episode_number', 'title', 'author', 'planned_release_date', 'status']);
    }

    public function test_contact_email_must_be_valid(): void
    {
        $user = $this->actingMember('Admin');

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', 'F40')
            ->set('title', 'Validierung')
            ->set('author', 'Autor')
            ->set('planned_release_date', '12.2025')
            ->set('status', 'Skripterstellung')
            ->set('progress', 10)
            ->set('roles', [
                [
                    'name' => 'Test', 'description' => 'Test', 'takes' => 1,
                    'member_id' => '', 'member_name' => '',
                    'contact_email' => 'keine-mail',
                    'speaker_pseudonym' => '', 'uploaded' => false,
                ],
            ])
            ->call('save')
            ->assertHasErrors(['roles.0.contact_email']);
    }

    public function test_invalid_planned_release_date_is_rejected(): void
    {
        $user = $this->actingMember('Admin');

        $invalidDates = ['13.2025', '99.9999', '3025', '01.13.2025', '30.02.2025', '31.04.2025', '2025-01-01'];

        foreach ($invalidDates as $date) {
            Livewire::actingAs($user)
                ->test(HoerbuchForm::class)
                ->set('episode_number', 'FX'.$date)
                ->set('title', 'Titel')
                ->set('author', 'Autor')
                ->set('planned_release_date', $date)
                ->set('status', 'Skripterstellung')
                ->set('progress', 0)
                ->call('save')
                ->assertHasErrors('planned_release_date');
        }
    }

    public function test_notes_are_sanitized_and_escaped_in_views(): void
    {
        $user = $this->actingMember('Admin');
        $malicious = '<script>alert("xss")</script>';
        $sanitized = 'alert("xss")';

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
            ->set('episode_number', 'F5')
            ->set('title', 'XSS Test')
            ->set('author', 'Autor')
            ->set('planned_release_date', '2025')
            ->set('status', 'Skripterstellung')
            ->set('progress', 0)
            ->set('notes', $malicious)
            ->call('save');

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

    // ── Edit / Update Tests ──────────────────────────────────────

    public function test_edit_form_displays_previous_speaker_hint(): void
    {
        $user = $this->actingMember('Admin');
        $actor = $this->actingMember();

        $earlier = AudiobookEpisode::create([
            'episode_number' => 'F29', 'title' => 'Frühere', 'author' => 'Autor',
            'planned_release_date' => '2024', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);
        $earlier->roles()->create(['name' => 'Matthew Drax', 'takes' => 1, 'user_id' => $actor->id]);

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F30', 'title' => 'Aktuelle', 'author' => 'Autor',
            'planned_release_date' => '2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 0, 'notes' => null,
        ]);
        $episode->roles()->create(['name' => 'Matthew Drax', 'takes' => 1]);

        // The previous speaker hint is rendered in the Alpine x-data JSON initialization
        $this->actingAs($user)
            ->get(route('hoerbuecher.edit', $episode))
            ->assertOk()
            ->assertSee('Bisheriger Sprecher: '.$actor->name);
    }

    public function test_edit_view_shows_compact_takes_column_and_checkbox_header(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F99', 'title' => 'Layout Test', 'author' => 'Autor',
            'planned_release_date' => '12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 10, 'notes' => null,
            'roles_total' => 1, 'roles_filled' => 1,
        ]);

        $episode->roles()->create([
            'name' => 'Testrolle', 'description' => 'Beschreibung', 'takes' => 3,
            'speaker_name' => 'Test Sprecher', 'uploaded' => true,
        ]);

        $response = $this->actingAs($user)->get(route('hoerbuecher.edit', $episode));

        $response->assertOk();
        $response->assertSee('id="roles-uploaded-header"', false);
        $response->assertSee('aria-labelledby="roles-uploaded-header"', false);
        $response->assertSee('md:max-w-[6rem]', false);
        $response->assertSee('max="999"', false);
    }

    public function test_edit_form_displays_contact_and_pseudonym_fields(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F33', 'title' => 'Bearbeitung', 'author' => 'Autor',
            'planned_release_date' => '2026', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $episode->roles()->create([
            'name' => 'Rolle', 'takes' => 1,
            'contact_email' => 'sichtbar@example.net',
            'speaker_pseudonym' => 'Alias X',
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.edit', $episode))
            ->assertOk()
            ->assertSee('sichtbar@example.net', false)
            ->assertSee('Alias X', false);
    }

    public function test_admin_can_update_episode(): void
    {
        $user = $this->actingMember('Admin');

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F2', 'title' => 'Alte Folge', 'author' => 'Autor',
            'planned_release_date' => '12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 10, 'roles_filled' => 5, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class, ['episode' => $episode])
            ->set('title', 'Neue Folge')
            ->set('author', 'Neuer Autor')
            ->set('planned_release_date', '2025')
            ->set('status', 'Veröffentlichung')
            ->set('progress', 100)
            ->set('notes', 'Aktualisiert')
            ->set('roles', [
                [
                    'name' => 'Neue Rolle', 'description' => 'desc', 'takes' => 1,
                    'member_name' => 'Extern', 'member_id' => '',
                    'contact_email' => 'rolle@example.org',
                    'speaker_pseudonym' => 'Shadow Voice',
                    'uploaded' => true,
                ],
            ])
            ->call('save')
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
            'episode_number' => 'F2', 'title' => 'Alt', 'author' => 'Autor',
            'planned_release_date' => '12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class, ['episode' => $episode])
            ->set('episode_number', '')
            ->set('title', '')
            ->set('author', '')
            ->set('planned_release_date', '')
            ->set('status', '')
            ->set('progress', 200)
            ->call('save')
            ->assertHasErrors(['episode_number', 'title', 'author', 'planned_release_date', 'status', 'progress']);
    }

    // ── AG / Team Tests ──────────────────────────────────────────

    public function test_ag_member_can_view_index_and_episode_but_not_edit(): void
    {
        $user = $this->actingAgMember();

        $episode = AudiobookEpisode::create([
            'episode_number' => 'F1', 'title' => 'Testfolge', 'author' => 'Autor',
            'planned_release_date' => '01.01.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 0, 'roles_filled' => 0, 'notes' => null,
        ]);

        $this->actingAs($user)
            ->get(route('hoerbuecher.index'))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.create'));

        $this->actingAs($user)
            ->get(route('hoerbuecher.show', $episode))
            ->assertOk()
            ->assertDontSee(route('hoerbuecher.edit', $episode));

        Livewire::actingAs($user)
            ->test(HoerbuchForm::class)
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
            'episode_number' => 'F99', 'title' => 'Upload Test', 'author' => 'Autor',
            'planned_release_date' => '01.01.2026', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $role = AudiobookRole::create([
            'episode_id' => $episode->id,
            'name' => 'Testrolle', 'description' => null, 'takes' => 0,
            'user_id' => null, 'speaker_name' => 'Speaker', 'uploaded' => false,
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
            'episode_number' => 'F98', 'title' => 'Upload Forbidden', 'author' => 'Autor',
            'planned_release_date' => '01.02.2026', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);

        $role = AudiobookRole::create([
            'episode_id' => $episode->id,
            'name' => 'Verboten', 'description' => null, 'takes' => 0,
            'user_id' => null, 'speaker_name' => 'Speaker', 'uploaded' => false,
        ]);

        $this->actingAs($member)
            ->from(route('hoerbuecher.show', $episode))
            ->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => true])
            ->assertForbidden();

        $this->assertFalse($role->fresh()->uploaded);
    }

    // ── JSON Endpoints ───────────────────────────────────────────

    public function test_previous_speaker_endpoint_returns_last_assigned_member(): void
    {
        $admin = $this->actingMember('Admin');
        $speaker1 = $this->actingMember();
        $speaker2 = $this->actingMember();

        $episode1 = AudiobookEpisode::create([
            'episode_number' => 'F29', 'title' => 'Ep1', 'author' => 'Autor',
            'planned_release_date' => '24.12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);
        AudiobookRole::create([
            'episode_id' => $episode1->id,
            'name' => 'Matthew Drax', 'takes' => 0, 'user_id' => $speaker1->id,
        ]);

        $episode2 = AudiobookEpisode::create([
            'episode_number' => 'F30', 'title' => 'Ep2', 'author' => 'Autor',
            'planned_release_date' => '25.12.2025', 'status' => 'Skripterstellung',
            'responsible_user_id' => null, 'progress' => 0,
            'roles_total' => 1, 'roles_filled' => 1, 'notes' => null,
        ]);
        AudiobookRole::create([
            'episode_id' => $episode2->id,
            'name' => 'Matthew Drax', 'takes' => 0, 'user_id' => $speaker2->id,
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

    // ── Navigation Tests ─────────────────────────────────────────

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

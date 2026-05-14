<?php

namespace Tests\Feature;

use App\Enums\MeetingRhythmType;
use App\Livewire\MeetingAdmin;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MeetingAdminTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    public function test_admin_page_loads_for_admin(): void
    {
        $this->actingAdmin();

        $this->get('/admin/treffen')
            ->assertOk()
            ->assertSee('Treffen - Admin');
    }

    public function test_admin_page_loads_for_vorstand(): void
    {
        $this->actingVorstand();

        $this->get('/admin/treffen')
            ->assertOk()
            ->assertSee('Treffen - Admin');
    }

    public function test_admin_page_forbidden_for_regular_member(): void
    {
        $this->actingMember();

        $this->get('/admin/treffen')
            ->assertForbidden();
    }

    public function test_admin_page_redirects_unauthenticated(): void
    {
        $this->get('/admin/treffen')
            ->assertRedirect('/login');
    }

    public function test_admin_can_create_meeting(): void
    {
        $admin = $this->actingAdmin();

        Livewire::test(MeetingAdmin::class)
            ->call('openForm')
            ->set('title', 'Vorstandsmeeting')
            ->set('slug', '')
            ->set('zoom_url', 'https://example.com/board')
            ->set('time_from', '19:00')
            ->set('time_to', '20:30')
            ->set('rhythm_type', MeetingRhythmType::MonthlyDayOfMonth->value)
            ->set('day_of_month', '3')
            ->call('save');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Vorstandsmeeting',
            'slug' => 'vorstandsmeeting',
            'zoom_url' => 'https://example.com/board',
            'rhythm_type' => MeetingRhythmType::MonthlyDayOfMonth->value,
            'day_of_month' => 3,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_admin_page_hides_form_until_opened(): void
    {
        $this->actingAdmin();

        Livewire::test(MeetingAdmin::class)
            ->assertDontSee('Treffen anlegen')
            ->call('openForm')
            ->assertSee('Treffen anlegen');
    }

    public function test_vorstand_can_update_meeting(): void
    {
        $vorstand = $this->actingVorstand();
        $meeting = Meeting::factory()->create([
            'title' => 'Alt',
            'slug' => 'alt',
            'zoom_url' => 'https://example.com/alt',
        ]);

        Livewire::test(MeetingAdmin::class)
            ->call('edit', $meeting->id)
            ->set('title', 'Aktualisiert')
            ->set('zoom_url', 'https://example.com/aktualisiert')
            ->set('rhythm_type', MeetingRhythmType::EveryNWeeks->value)
            ->set('starts_on', '2026-05-14')
            ->set('interval_weeks', '2')
            ->set('rhythm_note', 'Kickoff in Kalenderwoche 20')
            ->call('save');

        $meeting->refresh();

        $this->assertSame('Aktualisiert', $meeting->title);
        $this->assertSame(MeetingRhythmType::EveryNWeeks, $meeting->rhythm_type);
        $this->assertSame(2, $meeting->interval_weeks);
        $this->assertSame('2026-05-14', $meeting->starts_on?->format('Y-m-d'));
        $this->assertSame($vorstand->id, $meeting->updated_by);
    }

    public function test_validation_depends_on_rhythm_type(): void
    {
        $this->actingAdmin();

        Livewire::test(MeetingAdmin::class)
            ->call('openForm')
            ->set('title', 'Ohne Monatstag')
            ->set('zoom_url', 'https://example.com/meeting')
            ->set('rhythm_type', MeetingRhythmType::MonthlyDayOfMonth->value)
            ->set('day_of_month', '')
            ->call('save')
            ->assertHasErrors(['day_of_month']);
    }

    public function test_calculable_rhythm_requires_start_time(): void
    {
        $this->actingAdmin();

        Livewire::test(MeetingAdmin::class)
            ->call('openForm')
            ->set('title', 'Ohne Startzeit')
            ->set('zoom_url', 'https://example.com/meeting')
            ->set('rhythm_type', MeetingRhythmType::EveryNWeeks->value)
            ->set('starts_on', '2026-05-14')
            ->set('interval_weeks', '2')
            ->set('time_from', '')
            ->call('save')
            ->assertHasErrors(['time_from']);
    }

    public function test_admin_can_toggle_active_state(): void
    {
        $this->actingAdmin();
        $meeting = Meeting::factory()->create(['is_active' => true]);

        Livewire::test(MeetingAdmin::class)
            ->call('toggleActive', $meeting->id);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'is_active' => false,
        ]);
    }

    public function test_toggling_currently_edited_meeting_updates_form_state(): void
    {
        $this->actingAdmin();
        $meeting = Meeting::factory()->create(['is_active' => true]);

        Livewire::test(MeetingAdmin::class)
            ->call('edit', $meeting->id)
            ->assertSet('is_active', true)
            ->call('toggleActive', $meeting->id)
            ->assertSet('is_active', false);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_page_marks_zoom_fallback_as_configured(): void
    {
        $this->actingAdmin();
        config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');

        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => null,
        ]);

        Livewire::test(MeetingAdmin::class)
            ->assertSee('Fallback aus Konfiguration');
    }

    public function test_icon_only_actions_have_accessible_names_and_delete_prompt_is_not_double_escaped(): void
    {
        $this->actingAdmin();

        Meeting::factory()->create([
            'title' => 'Alpha & "Beta"',
            'slug' => 'alpha-beta',
        ]);

        $html = Livewire::test(MeetingAdmin::class)->html();

        $this->assertStringContainsString('aria-label="Treffen Alpha &amp; &quot;Beta&quot; nach oben verschieben"', $html);
        $this->assertStringContainsString('aria-label="Treffen Alpha &amp; &quot;Beta&quot; nach unten verschieben"', $html);
        $this->assertStringContainsString('aria-label="Treffen Alpha &amp; &quot;Beta&quot; bearbeiten"', $html);
        $this->assertStringContainsString('aria-label="Treffen Alpha &amp; &quot;Beta&quot; löschen"', $html);
        $this->assertStringContainsString('wire:confirm="Möchtest du das Treffen &quot;Alpha &amp;', $html);
        $this->assertStringNotContainsString('Alpha &amp;amp;', $html);
    }

    public function test_admin_can_delete_meeting(): void
    {
        $this->actingAdmin();
        $meeting = Meeting::factory()->create([
            'title' => 'Löschkandidat',
            'slug' => 'loeschkandidat',
        ]);

        Livewire::test(MeetingAdmin::class)
            ->call('delete', $meeting->id);

        $this->assertDatabaseMissing('meetings', [
            'id' => $meeting->id,
        ]);
    }

    public function test_deleting_currently_edited_meeting_closes_form(): void
    {
        $this->actingAdmin();
        $meeting = Meeting::factory()->create();

        Livewire::test(MeetingAdmin::class)
            ->call('edit', $meeting->id)
            ->assertSet('editingId', $meeting->id)
            ->assertSet('showForm', true)
            ->call('delete', $meeting->id)
            ->assertSet('editingId', null)
            ->assertSet('showForm', false)
            ->assertSet('title', '');
    }

    public function test_admin_can_reorder_meetings(): void
    {
        $this->actingAdmin();
        $first = Meeting::factory()->create([
            'title' => 'Sort A',
            'slug' => 'sort-a',
            'sort_order' => 100,
        ]);
        $second = Meeting::factory()->create([
            'title' => 'Sort B',
            'slug' => 'sort-b',
            'sort_order' => 110,
        ]);

        Livewire::test(MeetingAdmin::class)
            ->call('moveUp', $second->id);

        $this->assertDatabaseHas('meetings', [
            'id' => $first->id,
            'sort_order' => 110,
        ]);
        $this->assertDatabaseHas('meetings', [
            'id' => $second->id,
            'sort_order' => 100,
        ]);
    }

    public function test_admin_can_move_meeting_down(): void
    {
        $this->actingAdmin();
        $first = Meeting::factory()->create([
            'title' => 'Sort A',
            'slug' => 'sort-a',
            'sort_order' => 100,
        ]);
        $second = Meeting::factory()->create([
            'title' => 'Sort B',
            'slug' => 'sort-b',
            'sort_order' => 110,
        ]);

        Livewire::test(MeetingAdmin::class)
            ->call('moveDown', $first->id);

        $this->assertDatabaseHas('meetings', [
            'id' => $first->id,
            'sort_order' => 110,
        ]);
        $this->assertDatabaseHas('meetings', [
            'id' => $second->id,
            'sort_order' => 100,
        ]);
    }
}

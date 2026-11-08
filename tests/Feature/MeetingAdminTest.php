<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingRhythmType;
use App\Livewire\MeetingAdmin;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('admin page loads for admin', function (): void {
    $this->actingAdmin();
    $this->get('/admin/treffen')->assertOk()->assertSee('Treffen - Admin');
});

test('admin page loads for vorstand', function (): void {
    $this->actingVorstand();
    $this->get('/admin/treffen')->assertOk()->assertSee('Treffen - Admin');
});

test('admin page forbidden for regular member', function (): void {
    $this->actingMember();
    $this->get('/admin/treffen')->assertForbidden();
});

test('admin page redirects unauthenticated', function (): void {
    $this->get('/admin/treffen')->assertRedirect('/login');
});

test('admin can create meeting', function (): void {
    $admin = $this->actingAdmin();
    Livewire::test(MeetingAdmin::class)->call('openForm')->set('title', 'Vorstandsmeeting')->set('slug', '')->set('zoom_url', 'https://example.com/board')->set('time_from', '19:00')->set('time_to', '20:30')->set('rhythm_type', MeetingRhythmType::MonthlyDayOfMonth->value)->set('day_of_month', '3')->call('save');
    $this->assertDatabaseHas('meetings', ['title' => 'Vorstandsmeeting', 'slug' => 'vorstandsmeeting', 'zoom_url' => 'https://example.com/board', 'rhythm_type' => MeetingRhythmType::MonthlyDayOfMonth->value, 'day_of_month' => 3, 'updated_by' => $admin->id]);
});

test('admin page hides form until opened', function (): void {
    $this->actingAdmin();
    Livewire::test(MeetingAdmin::class)->assertDontSee('Treffen anlegen')->call('openForm')->assertSee('Treffen anlegen');
});

test('rhythm help preserves visible instructions and selected week', function (): void {
    $this->actingAdmin();
    Livewire::test(MeetingAdmin::class)->call('openForm')->set('week_of_month', '2')->assertSee('zweiten Dienstag im Monat')->assertSee('week-of-month-hint')->assertSet('week_of_month', '2')->set('rhythm_type', MeetingRhythmType::EveryNWeeks->value)->assertDontSee('week-of-month-hint')->assertSee('Startdatum');
});

test('vorstand can update meeting', function (): void {
    $vorstand = $this->actingVorstand();
    $meeting = Meeting::factory()->create(['title' => 'Alt', 'slug' => 'alt', 'zoom_url' => 'https://example.com/alt']);
    Livewire::test(MeetingAdmin::class)->call('edit', $meeting->id)->set('title', 'Aktualisiert')->set('zoom_url', 'https://example.com/aktualisiert')->set('rhythm_type', MeetingRhythmType::EveryNWeeks->value)->set('starts_on', '2026-05-14')->set('interval_weeks', '2')->set('rhythm_note', 'Kickoff in Kalenderwoche 20')->call('save');
    $meeting->refresh();
    expect($meeting->title)->toBe('Aktualisiert')
        ->and($meeting->rhythm_type)->toBe(MeetingRhythmType::EveryNWeeks)
        ->and($meeting->interval_weeks)->toBe(2)
        ->and($meeting->starts_on?->format('Y-m-d'))->toBe('2026-05-14')
        ->and($meeting->updated_by)->toBe($vorstand->id);
});

test('validation depends on rhythm type', function (): void {
    $this->actingAdmin();
    Livewire::test(MeetingAdmin::class)->call('openForm')->set('title', 'Ohne Monatstag')->set('zoom_url', 'https://example.com/meeting')->set('rhythm_type', MeetingRhythmType::MonthlyDayOfMonth->value)->set('day_of_month', '')->call('save')->assertHasErrors(['day_of_month']);
});

test('calculable rhythm requires start time', function (): void {
    $this->actingAdmin();
    Livewire::test(MeetingAdmin::class)->call('openForm')->set('title', 'Ohne Startzeit')->set('zoom_url', 'https://example.com/meeting')->set('rhythm_type', MeetingRhythmType::EveryNWeeks->value)->set('starts_on', '2026-05-14')->set('interval_weeks', '2')->set('time_from', '')->call('save')->assertHasErrors(['time_from']);
});

test('admin can toggle active state', function (): void {
    $this->actingAdmin();
    $meeting = Meeting::factory()->create(['is_active' => true]);
    Livewire::test(MeetingAdmin::class)->call('toggleActive', $meeting->id);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'is_active' => false]);
});

test('toggling currently edited meeting updates form state', function (): void {
    $this->actingAdmin();
    $meeting = Meeting::factory()->create(['is_active' => true]);
    Livewire::test(MeetingAdmin::class)->call('edit', $meeting->id)->assertSet('is_active', true)->call('toggleActive', $meeting->id)->assertSet('is_active', false);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'is_active' => false]);
});

test('admin page marks zoom fallback as configured', function (): void {
    $this->actingAdmin();
    config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => null]);
    Livewire::test(MeetingAdmin::class)->assertSee('Fallback aus Konfiguration');
});

test('icon only actions have accessible names and delete prompt is not double escaped', function (): void {
    $this->actingAdmin();
    Meeting::factory()->create(['title' => 'Alpha & "Beta"', 'slug' => 'alpha-beta']);
    $html = Livewire::test(MeetingAdmin::class)->html();
    expect($html)->toContain('aria-label="Treffen Alpha &amp; &quot;Beta&quot; nach oben verschieben"')
        ->toContain('aria-label="Treffen Alpha &amp; &quot;Beta&quot; nach unten verschieben"')
        ->toContain('aria-label="Treffen Alpha &amp; &quot;Beta&quot; bearbeiten"')
        ->toContain('aria-label="Treffen Alpha &amp; &quot;Beta&quot; löschen"')
        ->toContain('wire:confirm="Möchtest du das Treffen &quot;Alpha &amp;')->not->toContain('Alpha &amp;amp;');
});

test('admin can delete meeting', function (): void {
    $this->actingAdmin();
    $meeting = Meeting::factory()->create(['title' => 'Löschkandidat', 'slug' => 'loeschkandidat']);
    Livewire::test(MeetingAdmin::class)->call('delete', $meeting->id);
    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
});

test('deleting currently edited meeting closes form', function (): void {
    $this->actingAdmin();
    $meeting = Meeting::factory()->create();
    Livewire::test(MeetingAdmin::class)->call('edit', $meeting->id)->assertSet('editingId', $meeting->id)->assertSet('showForm', true)->call('delete', $meeting->id)->assertSet('editingId', null)->assertSet('showForm', false)->assertSet('title', '');
});

test('admin can reorder meetings', function (): void {
    $this->actingAdmin();
    $first = Meeting::factory()->create(['title' => 'Sort A', 'slug' => 'sort-a', 'sort_order' => 100]);
    $second = Meeting::factory()->create(['title' => 'Sort B', 'slug' => 'sort-b', 'sort_order' => 110]);
    Livewire::test(MeetingAdmin::class)->call('moveUp', $second->id);
    $this->assertDatabaseHas('meetings', ['id' => $first->id, 'sort_order' => 110]);
    $this->assertDatabaseHas('meetings', ['id' => $second->id, 'sort_order' => 100]);
});

test('admin can move meeting down', function (): void {
    $this->actingAdmin();
    $first = Meeting::factory()->create(['title' => 'Sort A', 'slug' => 'sort-a', 'sort_order' => 100]);
    $second = Meeting::factory()->create(['title' => 'Sort B', 'slug' => 'sort-b', 'sort_order' => 110]);
    Livewire::test(MeetingAdmin::class)->call('moveDown', $first->id);
    $this->assertDatabaseHas('meetings', ['id' => $first->id, 'sort_order' => 110]);
    $this->assertDatabaseHas('meetings', ['id' => $second->id, 'sort_order' => 100]);
});

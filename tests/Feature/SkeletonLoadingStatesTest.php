<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenAdminDashboard;
use App\Livewire\KassenbuchIndex;
use App\Livewire\KompendiumAdminDashboard;
use App\Livewire\MitgliederIndex;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class SkeletonLoadingStatesTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── MitgliederIndex ─────────────────────────────────────────

    public function test_mitglieder_index_renders_skeleton_for_loading_state(): void
    {
        $this->actingAs($this->actingMember());

        Livewire::test(MitgliederIndex::class)
            ->assertSee('skeleton', false)
            ->assertSee('wire:loading.delay', false);
    }

    public function test_mitglieder_index_skeleton_targets_sort_and_filter(): void
    {
        $this->actingAs($this->actingMember());

        $html = Livewire::test(MitgliederIndex::class)->html();

        $this->assertStringContainsString('wire:target="sort, nurOnline"', $html);
    }

    public function test_mitglieder_index_table_has_loading_remove(): void
    {
        $this->actingAs($this->actingMember());

        $html = Livewire::test(MitgliederIndex::class)->html();

        $this->assertStringContainsString('wire:loading.remove', $html);
    }

    // ── KassenbuchIndex ─────────────────────────────────────────

    public function test_kassenbuch_index_renders_skeleton_for_loading_state(): void
    {
        $this->actingAs($this->actingMember(Role::Kassenwart));

        Livewire::test(KassenbuchIndex::class)
            ->assertSee('skeleton', false);
    }

    public function test_kassenbuch_index_has_skeleton_for_payment_table(): void
    {
        $this->actingAs($this->actingMember(Role::Kassenwart));

        $html = Livewire::test(KassenbuchIndex::class)->html();

        $this->assertStringContainsString('wire:target="updatePayment"', $html);
    }

    public function test_kassenbuch_index_has_skeleton_for_entries_table(): void
    {
        $this->actingAs($this->actingMember(Role::Kassenwart));

        $html = Livewire::test(KassenbuchIndex::class)->html();

        $this->assertStringContainsString('wire:target="storeEntry, updateEntry, deleteEntry"', $html);
    }

    // ── FantreffenAdminDashboard ────────────────────────────────

    public function test_fantreffen_admin_dashboard_renders_skeleton_for_loading_state(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        $this->actingAs($admin);

        Livewire::test(FantreffenAdminDashboard::class)
            ->assertSee('skeleton', false)
            ->assertSee('wire:loading.delay', false);
    }

    public function test_fantreffen_admin_dashboard_skeleton_targets_filter_actions(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        $this->actingAs($admin);

        $html = Livewire::test(FantreffenAdminDashboard::class)->html();

        $this->assertStringContainsString('filterMemberStatus', $html);
        $this->assertStringContainsString('filterZahlungseingang', $html);
    }

    // ── KompendiumAdminDashboard ────────────────────────────────

    public function test_kompendium_admin_dashboard_renders_skeleton_for_loading_state(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        $this->actingAs($admin);

        Livewire::test(KompendiumAdminDashboard::class)
            ->assertSee('skeleton', false)
            ->assertSee('wire:loading.delay', false);
    }

    public function test_kompendium_admin_dashboard_skeleton_targets_crud_actions(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        $this->actingAs($admin);

        $html = Livewire::test(KompendiumAdminDashboard::class)->html();

        $this->assertStringContainsString('indexieren', $html);
        $this->assertStringContainsString('suchbegriff', $html);
    }
}

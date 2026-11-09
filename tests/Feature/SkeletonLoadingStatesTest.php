<?php

declare(strict_types=1);

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

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('mitglieder index renders skeleton for loading state', function (): void {
    $this->actingAs($this->actingMember());
    Livewire::test(MitgliederIndex::class)->assertSee('skeleton', false)->assertSee('wire:loading.delay', false);
});

test('mitglieder index skeleton targets sort and filter', function (): void {
    $this->actingAs($this->actingMember());
    $html = Livewire::test(MitgliederIndex::class)->html();
    // Prüfe einzelne Targets statt exakten String (robust bei Formatierungsänderungen)
    expect($html)->toMatch('/wire:target="[^"]*\bsort\b[^"]*"/');
    expect($html)->toMatch('/wire:target="[^"]*\bnurOnline\b[^"]*"/');
});

test('mitglieder index table has loading remove', function (): void {
    $this->actingAs($this->actingMember());
    $html = Livewire::test(MitgliederIndex::class)->html();
    expect($html)->toContain('wire:loading.remove');
});

test('kassenbuch index renders skeleton for loading state', function (): void {
    $this->actingAs($this->actingMember(Role::Kassenwart));
    Livewire::test(KassenbuchIndex::class)->assertSee('skeleton', false);
});

test('kassenbuch index has skeleton for payment table', function (): void {
    $this->actingAs($this->actingMember(Role::Kassenwart));
    $html = Livewire::test(KassenbuchIndex::class)->html();
    // Skeleton ohne wire:target — greift bei jedem Livewire-Request
    expect($html)->toContain('wire:loading.delay');
    expect($html)->toContain('wire:loading.remove');
});

test('kassenbuch index has skeleton for entries table', function (): void {
    $this->actingAs($this->actingMember(Role::Kassenwart));
    $html = Livewire::test(KassenbuchIndex::class)->html();
    // Beide Skeleton-Wrapper (Zahlungsstatus + Kassenbuch) ohne spezifisches Target
    expect(substr_count($html, 'wire:loading.delay'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($html, 'wire:loading.remove'))->toBeGreaterThanOrEqual(2);
});

test('fantreffen admin dashboard renders skeleton for loading state', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $this->actingAs($admin);
    Livewire::test(FantreffenAdminDashboard::class)->assertSee('skeleton', false)->assertSee('wire:loading.delay', false);
});

test('fantreffen admin dashboard skeleton targets filter actions', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $this->actingAs($admin);
    $html = Livewire::test(FantreffenAdminDashboard::class)->html();
    expect($html)->toContain('filterMemberStatus')
        ->toContain('filterZahlungseingang');
});

test('kompendium admin dashboard renders skeleton for loading state', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $this->actingAs($admin);
    Livewire::test(KompendiumAdminDashboard::class)->assertSee('skeleton', false)->assertSee('wire:loading.delay', false);
});

test('kompendium admin dashboard skeleton targets crud actions', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $this->actingAs($admin);
    $html = Livewire::test(KompendiumAdminDashboard::class)->html();
    expect($html)->toContain('indexieren')
        ->toContain('suchbegriff');
});

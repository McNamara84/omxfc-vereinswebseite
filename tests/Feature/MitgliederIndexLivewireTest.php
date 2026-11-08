<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MitgliederIndex;
use App\Models\MaddraxikonAccountLink;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('index sorts members by role desc', function (): void {
    $team = Team::membersTeam();
    $vorstand = User::factory()->create(['name' => 'Victor Vorstand', 'current_team_id' => $team->id]);
    $team->users()->attach($vorstand, ['role' => Role::Vorstand->value]);
    $kassenwart = User::factory()->create(['name' => 'Karl Kass', 'current_team_id' => $team->id]);
    $team->users()->attach($kassenwart, ['role' => Role::Kassenwart->value]);
    $ehren = User::factory()->create(['name' => 'Erika Ehren', 'current_team_id' => $team->id]);
    $team->users()->attach($ehren, ['role' => Role::Ehrenmitglied->value]);
    $acting = $this->actingMember('Mitglied');
    $acting->update(['name' => 'Aaron Actor']);
    $this->actingAs($acting);
    Livewire::test(MitgliederIndex::class)->set('sortBy', 'role')->set('sortDir', 'desc')->assertSeeInOrder(['Victor Vorstand', 'Aaron Actor', 'Karl Kass', 'Erika Ehren', 'Holger Ehrmann']);
});

test('index sorts members by visible nickname or name asc', function (): void {
    $team = Team::membersTeam();
    $a = User::factory()->create(['name' => 'Anna Alpha', 'vorname' => 'Anna', 'nachname' => 'Alpha', 'alias' => 'Zebra', 'current_team_id' => $team->id]);
    $team->users()->attach($a, ['role' => Role::Ehrenmitglied->value]);
    $z = User::factory()->create(['name' => 'Zara Zulu', 'vorname' => 'Zara', 'nachname' => 'Zulu', 'alias' => 'Abby', 'current_team_id' => $team->id]);
    $team->users()->attach($z, ['role' => Role::Kassenwart->value]);
    $blankAlias = User::factory()->create(['name' => 'Barry Blank', 'vorname' => 'Barry', 'nachname' => 'Blank', 'alias' => '   ', 'current_team_id' => $team->id]);
    $team->users()->attach($blankAlias, ['role' => Role::Mitglied->value]);
    $civilFallback = User::factory()->create(['name' => '   ', 'vorname' => 'Carl', 'nachname' => 'Civil', 'alias' => '   ', 'current_team_id' => $team->id]);
    $team->users()->attach($civilFallback, ['role' => Role::Mitglied->value]);
    $acting = $this->actingMember('Mitglied');
    $acting->update(['name' => 'Mike Member', 'vorname' => 'Mike', 'nachname' => 'Member']);
    $this->actingAs($acting);
    Livewire::test(MitgliederIndex::class)->set('sortBy', 'name')->set('sortDir', 'asc')->assertSeeInOrder(['Abby', 'Barry Blank', 'Carl Civil', 'Holger Ehrmann', 'Mike Member', 'Zebra']);
});

test('visible name sort runs in database with stable id tiebreaker', function (): void {
    $team = Team::membersTeam();
    $first = User::factory()->create(['name' => 'Anna Früh', 'vorname' => 'Anna', 'nachname' => 'Früh', 'alias' => 'Gleicher Nickname', 'current_team_id' => $team->id]);
    $team->users()->attach($first, ['role' => Role::Mitglied->value]);
    $second = User::factory()->create(['name' => 'Berta Spät', 'vorname' => 'Berta', 'nachname' => 'Spät', 'alias' => 'Gleicher Nickname', 'current_team_id' => $team->id]);
    $team->users()->attach($second, ['role' => Role::Mitglied->value]);
    $this->actingAs($this->actingMember('Admin'));
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(MitgliederIndex::class, ['sortBy' => 'name', 'sortDir' => 'desc'])->assertSeeInOrder(['Anna Früh', 'Berta Spät']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $sortQuery = collect($queries)->pluck('query')->map(fn (string $query): string => str_replace(['"', '`', '[', ']'], '', strtolower($query)))->first(fn (string $query): bool => str_contains($query, "coalesce(nullif(trim(users.alias), ''), nullif(trim(users.name), '')") && str_contains($query, ' desc'));
    expect($sortQuery)->not->toBeNull()
        ->toContain('users.vorname')
        ->toContain('users.nachname')
        ->toContain(strtolower(User::UNKNOWN_DISPLAY_NAME))
        ->toContain('users.id asc');
});

test('index shows alias author aliases and released contact links', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['name' => 'Stefan Kontakt', 'vorname' => 'Stefan', 'nachname' => 'Kontakt', 'current_team_id' => $team->id, 'alias' => 'Stefan K', 'author_aliases' => ['Ian Rolf Hill'], 'contact_release_maddraxikon' => true, 'contact_release_nextcloud' => true, 'nextcloud_username' => 'Holger']);
    MaddraxikonAccountLink::factory()->for($member)->create(['wiki_username' => 'Stefan K']);
    $team->users()->attach($member, ['role' => Role::Ehrenmitglied->value]);
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->assertSee('Stefan K')->assertDontSee('Alias: Stefan K')->assertDontSee('Stefan Kontakt')->assertSee('Ian Rolf Hill')->assertSee('Maddraxikon')->assertSee('Nextcloud')->assertSee('https://de.maddraxikon.com/index.php?title=Benutzer:Stefan_K', false)->assertSee('https://cloud.maddrax-fanclub.de/u/Holger', false);
});

test('index eager loads maddraxikon links for all members', function (): void {
    $team = Team::membersTeam();
    foreach (range(1, 3) as $index) {
        $member = User::factory()->create(['name' => "Wiki Mitglied {$index}", 'current_team_id' => $team->id, 'contact_release_maddraxikon' => true]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        MaddraxikonAccountLink::factory()->for($member)->create(['wiki_username' => "WikiName{$index}"]);
    }
    $this->actingAs($this->actingMember('Mitglied'));
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(MitgliederIndex::class)->assertSee('WikiName1')->assertSee('WikiName2')->assertSee('WikiName3');
    $linkQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains(strtolower($query), 'from "maddraxikon_account_links"'));
    DB::disableQueryLog();
    expect($linkQueries)->toHaveCount(1);
});

test('privileged member sees nickname and civil name', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['name' => 'Stefan Kontakt', 'vorname' => 'Stefan', 'nachname' => 'Kontakt', 'alias' => 'Stefan K', 'current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($this->actingMember('Admin'));
    Livewire::test(MitgliederIndex::class)->assertSee('Stefan K')->assertSee('Stefan Kontakt');
});

test('legacy nachname sort parameter maps to visible name', function (): void {
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class, ['sortBy' => 'nachname'])->assertSet('sortBy', 'name');
});

test('index sorts members by last activity desc', function (): void {
    $team = Team::membersTeam();
    $recent = User::factory()->create(['name' => 'Ralf Recent', 'current_team_id' => $team->id]);
    $team->users()->attach($recent, ['role' => Role::Mitglied->value]);
    $older = User::factory()->create(['name' => 'Olaf Old', 'current_team_id' => $team->id]);
    $team->users()->attach($older, ['role' => Role::Mitglied->value]);
    $older->forceFill(['last_activity' => now()->subMinutes(10)->timestamp])->save();
    $recent->forceFill(['last_activity' => now()->timestamp])->save();
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->set('sortBy', 'last_activity')->assertSeeInOrder(['Ralf Recent', 'Olaf Old']);
});

test('index falls back to visible name on invalid sort', function (): void {
    $team = Team::membersTeam();
    $first = User::factory()->create(['name' => 'Alice First', 'vorname' => 'Alice', 'nachname' => 'First', 'current_team_id' => $team->id]);
    $team->users()->attach($first, ['role' => Role::Mitglied->value]);
    $second = User::factory()->create(['name' => 'Bob Second', 'vorname' => 'Bob', 'nachname' => 'Second', 'current_team_id' => $team->id]);
    $team->users()->attach($second, ['role' => Role::Mitglied->value]);
    $acting = $this->actingMember('Mitglied');
    $acting->update(['name' => 'Charlie Current', 'vorname' => 'Charlie', 'nachname' => 'Current']);
    $this->actingAs($acting);
    Livewire::test(MitgliederIndex::class, ['sortBy' => 'foo'])->assertSet('sortBy', 'name')->assertSet('sortDir', 'asc')->assertSeeInOrder(['Alice First', 'Bob Second', 'Charlie Current', 'Holger Ehrmann']);
});

test('filter shows only online members', function (): void {
    $team = Team::membersTeam();
    $online = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($online, ['role' => Role::Mitglied->value]);
    $offline = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($offline, ['role' => Role::Mitglied->value]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $online->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp]);
    $this->actingAs($online);
    Livewire::test(MitgliederIndex::class)->set('nurOnline', true)->assertSee($online->name)->assertDontSee($offline->name);
});

test('index uses members team provider', function (): void {
    $team = Team::membersTeam();
    // Mock MUSS vor actingAs() registriert werden
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->atLeast()->once()->andReturn($team);
    });
    $user = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($user);
    Livewire::test(MitgliederIndex::class)->assertOk();
});

test('sort method toggles direction', function (): void {
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->assertSet('sortBy', 'name')->assertSet('sortDir', 'asc')->call('sort', 'name')->assertSet('sortDir', 'desc')->call('sort', 'name')->assertSet('sortDir', 'asc');
});

test('sort method changes column', function (): void {
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->assertSet('sortBy', 'name')->call('sort', 'mitglied_seit')->assertSet('sortBy', 'mitglied_seit')->assertSet('sortDir', 'asc');
});

test('sort last activity defaults to desc', function (): void {
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->call('sort', 'last_activity')->assertSet('sortBy', 'last_activity')->assertSet('sortDir', 'desc');
});

test('sort ignores invalid column', function (): void {
    $this->actingAs($this->actingMember('Mitglied'));
    Livewire::test(MitgliederIndex::class)->call('sort', 'email')->assertSet('sortBy', 'name');
});

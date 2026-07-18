<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MitgliederIndex;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MitgliederIndexLivewireTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_index_sorts_members_by_role_desc(): void
    {
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

        Livewire::test(MitgliederIndex::class)
            ->set('sortBy', 'role')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder([
                'Victor Vorstand',
                'Aaron Actor',
                'Karl Kass',
                'Erika Ehren',
                'Holger Ehrmann',
            ]);
    }

    public function test_index_sorts_members_by_visible_nickname_or_name_asc(): void
    {
        $team = Team::membersTeam();

        $a = User::factory()->create([
            'name' => 'Anna Alpha',
            'vorname' => 'Anna',
            'nachname' => 'Alpha',
            'alias' => 'Zebra',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($a, ['role' => Role::Ehrenmitglied->value]);

        $z = User::factory()->create([
            'name' => 'Zara Zulu',
            'vorname' => 'Zara',
            'nachname' => 'Zulu',
            'alias' => 'Abby',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($z, ['role' => Role::Kassenwart->value]);

        $blankAlias = User::factory()->create([
            'name' => 'Barry Blank',
            'vorname' => 'Barry',
            'nachname' => 'Blank',
            'alias' => '   ',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($blankAlias, ['role' => Role::Mitglied->value]);

        $acting = $this->actingMember('Mitglied');
        $acting->update([
            'name' => 'Mike Member',
            'vorname' => 'Mike',
            'nachname' => 'Member',
        ]);
        $this->actingAs($acting);

        Livewire::test(MitgliederIndex::class)
            ->set('sortBy', 'name')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder([
                'Abby',
                'Barry Blank',
                'Holger Ehrmann',
                'Mike Member',
                'Zebra',
            ]);
    }

    public function test_visible_name_sort_runs_in_database_with_stable_id_tiebreaker(): void
    {
        $team = Team::membersTeam();

        $first = User::factory()->create([
            'name' => 'Anna Früh',
            'vorname' => 'Anna',
            'nachname' => 'Früh',
            'alias' => 'Gleicher Nickname',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($first, ['role' => Role::Mitglied->value]);

        $second = User::factory()->create([
            'name' => 'Berta Spät',
            'vorname' => 'Berta',
            'nachname' => 'Spät',
            'alias' => 'Gleicher Nickname',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($second, ['role' => Role::Mitglied->value]);

        $this->actingAs($this->actingMember('Admin'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(MitgliederIndex::class, ['sortBy' => 'name', 'sortDir' => 'desc'])
            ->assertSeeInOrder(['Anna Früh', 'Berta Spät']);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $sortQuery = collect($queries)
            ->pluck('query')
            ->first(fn (string $query): bool => str_contains(
                strtolower($query),
                "coalesce(nullif(trim(users.alias), ''), trim(users.name)) desc"
            ));

        $this->assertNotNull($sortQuery);

        $normalizedQuery = str_replace(['"', '`'], '', strtolower($sortQuery));
        $this->assertStringContainsString('users.id asc', $normalizedQuery);
    }

    public function test_index_shows_alias_author_aliases_and_released_contact_links(): void
    {
        $team = Team::membersTeam();

        $member = User::factory()->create([
            'name' => 'Stefan Kontakt',
            'vorname' => 'Stefan',
            'nachname' => 'Kontakt',
            'current_team_id' => $team->id,
            'alias' => 'Stefan K',
            'author_aliases' => ['Ian Rolf Hill'],
            'contact_release_maddraxikon' => true,
            'contact_release_nextcloud' => true,
            'maddraxikon_username' => 'Stefan K',
            'nextcloud_username' => 'Holger',
        ]);
        $team->users()->attach($member, ['role' => Role::Ehrenmitglied->value]);

        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->assertSee('Stefan K')
            ->assertDontSee('Alias: Stefan K')
            ->assertDontSee('Stefan Kontakt')
            ->assertSee('Ian Rolf Hill')
            ->assertSee('Maddraxikon')
            ->assertSee('Nextcloud')
            ->assertSee('https://de.maddraxikon.com/index.php?title=Benutzer:Stefan_K', false)
            ->assertSee('https://cloud.maddrax-fanclub.de/u/Holger', false);
    }

    public function test_privileged_member_sees_nickname_and_civil_name(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create([
            'name' => 'Stefan Kontakt',
            'vorname' => 'Stefan',
            'nachname' => 'Kontakt',
            'alias' => 'Stefan K',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $this->actingAs($this->actingMember('Admin'));

        Livewire::test(MitgliederIndex::class)
            ->assertSee('Stefan K')
            ->assertSee('Stefan Kontakt');
    }

    public function test_legacy_nachname_sort_parameter_maps_to_visible_name(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class, ['sortBy' => 'nachname'])
            ->assertSet('sortBy', 'name');
    }

    public function test_index_sorts_members_by_last_activity_desc(): void
    {
        $team = Team::membersTeam();

        $recent = User::factory()->create(['name' => 'Ralf Recent', 'current_team_id' => $team->id]);
        $team->users()->attach($recent, ['role' => Role::Mitglied->value]);

        $older = User::factory()->create(['name' => 'Olaf Old', 'current_team_id' => $team->id]);
        $team->users()->attach($older, ['role' => Role::Mitglied->value]);

        $older->forceFill(['last_activity' => now()->subMinutes(10)->timestamp])->save();
        $recent->forceFill(['last_activity' => now()->timestamp])->save();

        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->set('sortBy', 'last_activity')
            ->assertSeeInOrder(['Ralf Recent', 'Olaf Old']);
    }

    public function test_index_falls_back_to_visible_name_on_invalid_sort(): void
    {
        $team = Team::membersTeam();

        $first = User::factory()->create([
            'name' => 'Alice First',
            'vorname' => 'Alice',
            'nachname' => 'First',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($first, ['role' => Role::Mitglied->value]);

        $second = User::factory()->create([
            'name' => 'Bob Second',
            'vorname' => 'Bob',
            'nachname' => 'Second',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($second, ['role' => Role::Mitglied->value]);

        $acting = $this->actingMember('Mitglied');
        $acting->update([
            'name' => 'Charlie Current',
            'vorname' => 'Charlie',
            'nachname' => 'Current',
        ]);
        $this->actingAs($acting);

        Livewire::test(MitgliederIndex::class, ['sortBy' => 'foo'])
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDir', 'asc')
            ->assertSeeInOrder([
                'Alice First',
                'Bob Second',
                'Charlie Current',
                'Holger Ehrmann',
            ]);
    }

    public function test_filter_shows_only_online_members(): void
    {
        $team = Team::membersTeam();

        $online = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($online, ['role' => Role::Mitglied->value]);

        $offline = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($offline, ['role' => Role::Mitglied->value]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $online->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($online);

        Livewire::test(MitgliederIndex::class)
            ->set('nurOnline', true)
            ->assertSee($online->name)
            ->assertDontSee($offline->name);
    }

    public function test_index_uses_members_team_provider(): void
    {
        $team = Team::membersTeam();

        // Mock MUSS vor actingAs() registriert werden
        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->atLeast()->once()->andReturn($team);
        });

        $user = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($user);

        Livewire::test(MitgliederIndex::class)
            ->assertOk();
    }

    public function test_sort_method_toggles_direction(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->assertSet('sortBy', 'name')
            ->assertSet('sortDir', 'asc')
            ->call('sort', 'name')
            ->assertSet('sortDir', 'desc')
            ->call('sort', 'name')
            ->assertSet('sortDir', 'asc');
    }

    public function test_sort_method_changes_column(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->assertSet('sortBy', 'name')
            ->call('sort', 'mitglied_seit')
            ->assertSet('sortBy', 'mitglied_seit')
            ->assertSet('sortDir', 'asc');
    }

    public function test_sort_last_activity_defaults_to_desc(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->call('sort', 'last_activity')
            ->assertSet('sortBy', 'last_activity')
            ->assertSet('sortDir', 'desc');
    }

    public function test_sort_ignores_invalid_column(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->call('sort', 'email')
            ->assertSet('sortBy', 'name');
    }
}

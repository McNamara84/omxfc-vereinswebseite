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

    public function test_index_sorts_members_by_nachname_asc(): void
    {
        $team = Team::membersTeam();

        $a = User::factory()->create([
            'name' => 'Anna Alpha',
            'vorname' => 'Anna',
            'nachname' => 'Alpha',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($a, ['role' => Role::Ehrenmitglied->value]);

        $z = User::factory()->create([
            'name' => 'Zara Zulu',
            'vorname' => 'Zara',
            'nachname' => 'Zulu',
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($z, ['role' => Role::Kassenwart->value]);

        $acting = $this->actingMember('Mitglied');
        $acting->update([
            'name' => 'Mike Member',
            'vorname' => 'Mike',
            'nachname' => 'Member',
        ]);
        $this->actingAs($acting);

        Livewire::test(MitgliederIndex::class)
            ->set('sortBy', 'nachname')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder([
                'Anna Alpha',
                'Holger Ehrmann',
                'Mike Member',
                'Zara Zulu',
            ]);
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

    public function test_index_falls_back_to_nachname_on_invalid_sort(): void
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
            ->assertSet('sortBy', 'nachname')
            ->assertSet('sortDir', 'asc')
            ->assertSeeInOrder([
                'Charlie Current',
                'Holger Ehrmann',
                'Alice First',
                'Bob Second',
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
            ->assertSet('sortBy', 'nachname')
            ->assertSet('sortDir', 'asc')
            ->call('sort', 'nachname')
            ->assertSet('sortDir', 'desc')
            ->call('sort', 'nachname')
            ->assertSet('sortDir', 'asc');
    }

    public function test_sort_method_changes_column(): void
    {
        $this->actingAs($this->actingMember('Mitglied'));

        Livewire::test(MitgliederIndex::class)
            ->assertSet('sortBy', 'nachname')
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
            ->assertSet('sortBy', 'nachname');
    }
}

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

        $component = Livewire::test(MitgliederIndex::class, ['sort' => 'role', 'dir' => 'desc']);

        $members = $component->viewData('members');
        $roles = $members->pluck('membership.role')->all();

        $this->assertSame([
            'Vorstand',
            'Mitglied',
            'Kassenwart',
            'Ehrenmitglied',
            'Admin',
        ], $roles);
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

        $component = Livewire::test(MitgliederIndex::class, ['sort' => 'nachname', 'dir' => 'asc']);

        $members = $component->viewData('members');
        $names = $members->pluck('name')->all();

        $this->assertSame([
            'Anna Alpha',
            'Holger Ehrmann',
            'Mike Member',
            'Zara Zulu',
        ], $names);
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

        $component = Livewire::test(MitgliederIndex::class, ['sort' => 'last_activity']);

        $members = $component->viewData('members');
        $names = $members->pluck('name')
            ->filter(fn ($name) => in_array($name, ['Ralf Recent', 'Olaf Old']))
            ->values()
            ->all();

        $this->assertSame(['Ralf Recent', 'Olaf Old'], $names);
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

        $component = Livewire::test(MitgliederIndex::class, ['sort' => 'foo']);

        $this->assertSame('nachname', $component->get('sortBy'));
        $this->assertSame('asc', $component->get('sortDir'));

        $members = $component->viewData('members');
        $names = $members->pluck('name')->all();

        $this->assertSame([
            'Charlie Current',
            'Holger Ehrmann',
            'Alice First',
            'Bob Second',
        ], $names);
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

        $component = Livewire::test(MitgliederIndex::class)
            ->set('nurOnline', true);

        $members = $component->viewData('members');
        $this->assertCount(1, $members);
        $this->assertTrue($members->contains('id', $online->id));
    }

    public function test_index_uses_members_team_provider(): void
    {
        $team = Team::membersTeam();

        // Mock MUSS vor actingAs() registriert werden
        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->andReturn($team);
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

<?php

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Memberships\MembershipContext;
use App\Services\UserRoleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\UsesCommittedMembershipDatabase;
use Tests\TestCase;

class MembershipContextTest extends TestCase
{
    use CreatesUserWithRole;
    use UsesCommittedMembershipDatabase;

    public static function roles(): array
    {
        return array_map(fn (Role $role): array => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_all_adapters_share_one_lookup_across_model_instances(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $copy = $user->fresh();
        $team = Team::membersTeam();
        DB::enableQueryLog();
        $this->assertSame($role, $user->role());
        $this->assertSame($role, $copy->mitgliederTeamRole());
        $this->assertSame($role, (new UserRoleService)->getRole($copy, $team));
        $this->assertTrue($team->hasUserWithRole($user, $role->value));
        foreach (Role::cases() as $other) {
            $this->assertSame($role === $other, $team->hasUserWithRole($user, $other->value));
        }
        $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['Gast'])]
    public function test_invalid_roles_are_distinct_from_missing_membership(?string $rawRole): void
    {
        $user = User::factory()->create();
        $team = Team::membersTeam();
        $context = app(MembershipContext::class);
        $missing = $context->forUserInTeam($user, $team);
        $this->assertFalse($missing->exists);
        $this->assertNull($missing->role);
        $this->assertSame($missing, $context->forUserInTeam($user, $team));
        $team->users()->attach($user, ['role' => $rawRole]);
        $present = $context->forUserInTeam($user, $team);
        $this->assertTrue($present->exists);
        $this->assertSame($rawRole, $present->rawRole);
        $this->assertNull($present->role);
        $this->assertSame($present, $context->forUserInTeam($user, $team));
    }

    public function test_raw_strings_preserve_sql_comparison_and_cache_negative_results(): void
    {
        $user = $this->createUserWithRole('admin');
        $team = Team::membersTeam();
        $this->assertNull($user->mitgliederTeamRole());
        DB::enableQueryLog();
        $this->assertFalse($team->hasUserWithRole($user, 'Admin'));
        $this->assertFalse($team->hasUserWithRole($user, 'Admin'));
        $this->assertTrue($team->hasUserWithRole($user, 'admin'));
        $this->assertTrue($team->hasUserWithRole($user, 'admin'));
        $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
    }

    public function test_missing_membership_is_memoized_and_does_not_grant_owner_role(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        DB::enableQueryLog();
        $this->assertNull($user->role());
        $this->assertFalse($team->hasUserWithRole($user, 'Admin'));
        $this->assertFalse($team->hasUserWithRole($user, 'Gast'));
        $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertTrue($user->hasTeamRole($team, Role::Admin->value));
        $this->assertTrue($user->hasTeamPermission($team, 'update'));
    }

    public function test_active_team_and_members_team_and_other_users_are_separate(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $other = $this->createUserWithRole(Role::Mitglied);
        $ag = Team::factory()->create();
        $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
        $user->switchTeam($ag);
        $this->assertSame(Role::Mitwirkender, $user->role());
        $this->assertSame(Role::Admin, $user->mitgliederTeamRole());
        $this->assertSame(Role::Mitglied, $other->role());
        $this->assertFalse($user->hasRole(Role::Admin));
    }

    public function test_navigation_keeps_first_matching_members_team_when_legacy_names_are_duplicated(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $duplicate = Team::factory()->create(['name' => 'Mitglieder']);
        $duplicate->users()->attach($user, ['role' => Role::Admin->value]);
        $this->assertSame(Role::Mitglied, app(MembershipContext::class)->navigationRoles($user)['members']);
    }

    public function test_connections_with_identical_ids_are_separate(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        config(['database.connections.membership_other' => config('database.connections.sqlite')]);
        Schema::connection('membership_other')->create('users', fn (Blueprint $table) => $table->integer('id'));
        DB::connection('membership_other')->table('users')->insert(['id' => $user->id]);
        Schema::connection('membership_other')->create('team_user', function (Blueprint $table): void {
            $table->integer('user_id');
            $table->integer('team_id');
            $table->string('role')->nullable();
        });
        DB::connection('membership_other')->table('team_user')->insert(['user_id' => $user->id, 'team_id' => $team->id, 'role' => Role::Mitglied->value]);
        $context = app(MembershipContext::class);
        $this->assertSame(Role::Admin, $context->forUserInTeam($user, $team)->role);
        $this->assertSame(Role::Mitglied, $context->forUserInTeam((clone $user)->setConnection('membership_other'), (clone $team)->setConnection('membership_other'))->role);
        $this->assertSame(Role::Admin, $context->forUserInTeam($user, $team)->role);
        DB::purge('membership_other');
    }

    public function test_native_token_permissions_are_preserved(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $user->withAccessToken(new PersonalAccessToken(['abilities' => ['read']]));
        $this->assertSame(Role::Admin, $user->role());
        $this->assertTrue($user->hasTeamPermission($team, 'read'));
        $this->assertFalse($user->hasTeamPermission($team, 'update'));
    }

    public function test_raw_role_check_rejects_an_orphaned_pivot_while_typed_adapter_keeps_its_contract(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $this->assertTrue($team->hasUserWithRole($user, Role::Admin->value));
        $user->delete();
        $this->assertSame(Role::Admin, (new UserRoleService)->getRole($user, $team));
        $this->assertFalse($team->hasUserWithRole($user, Role::Admin->value));
        app(MembershipContext::class)->reset();
        app(MembershipContext::class)->navigationRoles($user);
        $this->assertFalse($team->hasUserWithRole($user, Role::Admin->value));
    }

    public function test_user_without_active_or_members_team_has_no_role(): void
    {
        $user = new User;
        $this->assertNull($user->role());
        Team::membersTeam()->delete();
        $this->assertNull($user->mitgliederTeamRole());
    }

    public function test_explicit_fresh_read_discards_an_earlier_snapshot(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $context = app(MembershipContext::class);
        $this->assertSame(Role::Admin, $user->role());
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        $this->assertSame(Role::Admin, $user->role());
        $this->assertSame(Role::Mitglied, $context->freshForUserInTeam($user, $team)->role);
        $this->assertSame(Role::Mitglied, $user->role());
    }

    public function test_fresh_scope_keeps_raw_sql_and_named_membership_checks_current(): void
    {
        $user = $this->createUserWithRole('admin');
        $team = Team::membersTeam();
        $context = app(MembershipContext::class);
        $context->runFresh(function () use ($user, $team): void {
            $this->assertTrue($team->hasUserWithRole($user, 'admin'));
            $this->assertTrue($user->isMemberOfTeam('Mitglieder'));
            $this->assertFalse($user->isOwnerOfTeam('Missing'));
            DB::table('team_user')->where('user_id', $user->id)->delete();
            $this->assertFalse($team->hasUserWithRole($user, 'admin'));
            $this->assertFalse($user->isMemberOfTeam('Mitglieder'));
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Memberships\MembershipContext;
use App\Services\UserRoleService;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

pest()->extend(MembershipContextTestFixtures::class);
dataset('MembershipContextTest.roles', static fn (): array => array_map(fn (Role $role): array => [$role], Role::cases()));
test('all adapters share one lookup across model instances', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $copy = $user->fresh();
    $team = Team::membersTeam();
    DB::enableQueryLog();
    expect($user->role())->toBe($role)
        ->and($copy->mitgliederTeamRole())->toBe($role)
        ->and((new UserRoleService)->getRole($copy, $team))->toBe($role)
        ->and($team->hasUserWithRole($user, $role->value))->toBeTrue();
    foreach (Role::cases() as $other) {
        expect($team->hasUserWithRole($user, $other->value))->toBe($role === $other);
    }
    $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1);
})->with('MembershipContextTest.roles');
test('invalid roles are distinct from missing membership', function (?string $rawRole): void {
    $user = User::factory()->create();
    $team = Team::membersTeam();
    $context = app(MembershipContext::class);
    $missing = $context->forUserInTeam($user, $team);
    expect($missing->exists)->toBeFalse()
        ->and($missing->role)->toBeNull()
        ->and($context->forUserInTeam($user, $team))->toBe($missing);
    $team->users()->attach($user, ['role' => $rawRole]);
    $present = $context->forUserInTeam($user, $team);
    expect($present->exists)->toBeTrue()
        ->and($present->rawRole)->toBe($rawRole)
        ->and($present->role)->toBeNull()
        ->and($context->forUserInTeam($user, $team))->toBe($present);
})->with([[null], [''], ['Gast']]);
test('raw strings preserve sql comparison and cache negative results', function (): void {
    $user = $this->createUserWithRole('admin');
    $team = Team::membersTeam();
    expect($user->mitgliederTeamRole())->toBeNull();
    DB::enableQueryLog();
    expect($team->hasUserWithRole($user, 'Admin'))->toBeFalse()
        ->and($team->hasUserWithRole($user, 'Admin'))->toBeFalse()
        ->and($team->hasUserWithRole($user, 'admin'))->toBeTrue()
        ->and($team->hasUserWithRole($user, 'admin'))->toBeTrue();
    $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
    DB::disableQueryLog();
    expect($queries)->toHaveCount(2);
});
test('missing membership is memoized and does not grant owner role', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $user->forceFill(['current_team_id' => $team->id])->save();
    DB::enableQueryLog();
    expect($user->role())->toBeNull()
        ->and($team->hasUserWithRole($user, 'Admin'))->toBeFalse()
        ->and($team->hasUserWithRole($user, 'Gast'))->toBeFalse();
    $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'team_user'));
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1)
        ->and($user->hasTeamRole($team, Role::Admin->value))->toBeTrue()
        ->and($user->hasTeamPermission($team, 'update'))->toBeTrue();
});
test('active team and members team and other users are separate', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $other = $this->createUserWithRole(Role::Mitglied);
    $ag = Team::factory()->create();
    $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
    $user->switchTeam($ag);
    expect($user->role())->toBe(Role::Mitwirkender)
        ->and($user->mitgliederTeamRole())->toBe(Role::Admin)
        ->and($other->role())->toBe(Role::Mitglied)
        ->and($user->hasRole(Role::Admin))->toBeFalse();
});
test('navigation keeps first matching members team when legacy names are duplicated', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $duplicate = Team::factory()->create(['name' => 'Mitglieder']);
    $duplicate->users()->attach($user, ['role' => Role::Admin->value]);
    expect(app(MembershipContext::class)->navigationRoles($user)['members'])->toBe(Role::Mitglied);
});
test('navigation build reuses team data in a cacheable read scope', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    foreach (['AG Fanhörbücher', 'AG Maddraxikon'] as $name) {
        $team = Team::factory()->create(['name' => $name, 'user_id' => $user->id, 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    }
    expect(app(NavigationContext::class)->canAccessMembers($user))->toBeTrue();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $navigation = app(NavigationBuilder::class)->build($user);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $section = collect($navigation['sections'])->firstWhere('title', 'Projekte & Mitmachen');
    expect($section)->not->toBeNull()
        ->and(array_column($section['items'], 'title'))->toContain('AG verwalten')
        ->and(count($queries))->toBeLessThanOrEqual(3);
});
test('connections with identical ids are separate', function (): void {
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
    expect($context->forUserInTeam($user, $team)->role)->toBe(Role::Admin)
        ->and($context->forUserInTeam((clone $user)->setConnection('membership_other'), (clone $team)->setConnection('membership_other'))->role)->toBe(Role::Mitglied)
        ->and($context->forUserInTeam($user, $team)->role)->toBe(Role::Admin);
    DB::purge('membership_other');
});
test('native token permissions are preserved', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    $user->withAccessToken(new PersonalAccessToken(['abilities' => ['read']]));
    expect($user->role())->toBe(Role::Admin)
        ->and($user->hasTeamPermission($team, 'read'))->toBeTrue()
        ->and($user->hasTeamPermission($team, 'update'))->toBeFalse();
});
test('raw role check rejects an orphaned pivot while typed adapter keeps its contract', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    expect($team->hasUserWithRole($user, Role::Admin->value))->toBeTrue();
    $user->delete();
    expect((new UserRoleService)->getRole($user, $team))->toBe(Role::Admin)
        ->and($team->hasUserWithRole($user, Role::Admin->value))->toBeFalse();
    app(MembershipContext::class)->reset();
    app(MembershipContext::class)->navigationRoles($user);
    expect($team->hasUserWithRole($user, Role::Admin->value))->toBeFalse();
});
test('user without active or members team has no role', function (): void {
    $user = new User;
    expect($user->role())->toBeNull();
    Team::membersTeam()->delete();
    expect($user->mitgliederTeamRole())->toBeNull();
});
test('explicit fresh read discards an earlier snapshot', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    $context = app(MembershipContext::class);
    expect($user->role())->toBe(Role::Admin);
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
    expect($user->role())->toBe(Role::Admin)
        ->and($context->freshForUserInTeam($user, $team)->role)->toBe(Role::Mitglied)
        ->and($user->role())->toBe(Role::Mitglied);
});
test('fresh read prevents navigation from restoring revoked roles', function (?Role $newRole): void {
    $user = $this->createUserWithRole(Role::Admin);
    $copy = $user->fresh();
    $team = Team::membersTeam();
    $context = app(MembershipContext::class);
    expect($context->navigationRoles($user)['current'])->toBe(Role::Admin)
        ->and($context->navigationRoles($copy)['members'])->toBe(Role::Admin);
    // Bypass model events to reproduce a change discovered by an explicit fresh read.
    $membership = DB::table('team_user')->where('team_id', $team->id)->where('user_id', $user->id);
    $newRole ? $membership->update(['role' => $newRole->value]) : $membership->delete();
    expect($context->freshForUserInTeam($copy, $team)->role)->toBe($newRole)
        ->and($context->navigationRoles($user)['current'])->toBe($newRole)
        ->and($context->navigationRoles($copy)['members'])->toBe($newRole)
        ->and($user->role())->toBe($newRole)
        ->and($copy->mitgliederTeamRole())->toBe($newRole)
        ->and($user->hasRole(Role::Admin))->toBeFalse()
        ->and($team->hasUserWithRole($copy, Role::Admin->value))->toBeFalse();
})->with([[Role::Mitglied], [null]]);
test('fresh scope keeps raw sql and named membership checks current', function (): void {
    $user = $this->createUserWithRole('admin');
    $team = Team::membersTeam();
    $context = app(MembershipContext::class);
    $context->runFresh(function () use ($user, $team): void {
        expect($team->hasUserWithRole($user, 'admin'))->toBeTrue()
            ->and($user->isMemberOfTeam('Mitglieder'))->toBeTrue()
            ->and($user->isOwnerOfTeam('Missing'))->toBeFalse();
        DB::table('team_user')->where('user_id', $user->id)->delete();
        expect($team->hasUserWithRole($user, 'admin'))->toBeFalse()
            ->and($user->isMemberOfTeam('Mitglieder'))->toBeFalse();
    });
});

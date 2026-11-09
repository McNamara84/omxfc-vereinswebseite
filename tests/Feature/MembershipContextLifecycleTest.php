<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\ResetMembershipContext;
use App\Livewire\MitgliederIndex;
use App\Models\Team;
use App\Services\Memberships\MembershipContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Mockery;
use RuntimeException;

pest()->extend(MembershipContextLifecycleTestFixtures::class);

test('two http requests and user switches use new data', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $member = $this->createUserWithRole(Role::Mitglied);
    Route::get('/_testing/membership-context', fn () => response()->json(['role' => auth()->user()->role()?->value]));
    $this->actingAs($admin)->get('/_testing/membership-context')->assertJson(['role' => Role::Admin->value]);
    DB::table('team_user')->where('user_id', $admin->id)->update(['role' => Role::Vorstand->value]);
    $this->get('/_testing/membership-context')->assertJson(['role' => Role::Vorstand->value]);
    $this->actingAs($member)->get('/_testing/membership-context')->assertJson(['role' => Role::Mitglied->value]);
});

test('unsafe http requests always read fresh membership', function (string $method): void {
    $user = $this->createUserWithRole(Role::Admin);
    Route::match([$method], '/_testing/membership-write', function () use ($user) {
        $before = $user->role();
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);

        return response()->json(['before' => $before->value, 'after' => $user->role()->value]);
    });
    $this->actingAs($user)->call($method, '/_testing/membership-write')->assertOk()->assertJson(['before' => Role::Admin->value, 'after' => Role::Mitglied->value]);
})->with([['POST'], ['PATCH'], ['DELETE']]);

test('exception resets http context and restores memoization', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $context = app(MembershipContext::class);
    try {
        (new ResetMembershipContext)->handle(Request::create('/', 'POST'), function () use ($user) {
            expect($user->role())->toBe(Role::Admin);
            DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
            throw new RuntimeException('expected');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('expected');
    }
    $snapshot = $context->forUserInTeam($user, Team::membersTeam());
    expect($snapshot->role)->toBe(Role::Mitglied)
        ->and($context->forUserInTeam($user, Team::membersTeam()))->toBe($snapshot);
});

test('livewire followup request observes revoked privilege', function (): void {
    $user = $this->actingMember(Role::Admin);
    $component = Livewire::test(MitgliederIndex::class);
    expect($component->instance()->canViewDetails)->toBeTrue();
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
    $component->call('sort', 'name')->assertOk();
    expect($component->instance()->canViewDetails)->toBeFalse();
});

test('job boundaries discard context on success and failure', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('uuid')->andReturn('membership-test');
    $job->shouldReceive('resolveName')->andReturn('MembershipTestJob');
    $job->shouldReceive('payload')->andReturn([]);
    foreach ([new JobProcessing('sync', $job), new JobProcessed('sync', $job), new JobExceptionOccurred('sync', $job, new RuntimeException('expected'))] as $event) {
        expect($user->role())->toBe(Role::Admin);
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        Event::dispatch($event);
        expect($user->role())->toBe(Role::Mitglied);
        Team::membersTeam()->users()->updateExistingPivot($user->id, ['role' => Role::Admin->value]);
    }
});

test('commands reset context before and after execution', function (): void {
    $kernel = app(Kernel::class);
    // Laravel deliberately disables Symfony command events in unit tests.
    $kernel->rerouteSymfonyCommandEvents()->setArtisan(null);
    $user = $this->createUserWithRole(Role::Admin);
    expect($user->role())->toBe(Role::Admin);
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
    $testCase = $this;
    $kernel->registerCommand(new ClosureCommand('membership-context:probe', function () use ($user, $testCase): void {
        $testCase->assertSame(Role::Mitglied, $user->role());
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Vorstand->value]);
    }));
    $this->artisan('membership-context:probe')->assertSuccessful();
    expect($user->role())->toBe(Role::Vorstand);
});

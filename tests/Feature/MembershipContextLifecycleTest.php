<?php

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
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\UsesCommittedMembershipDatabase;
use Tests\TestCase;

class MembershipContextLifecycleTest extends TestCase
{
    use CreatesUserWithRole;
    use UsesCommittedMembershipDatabase;

    public function test_two_http_requests_and_user_switches_use_new_data(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $member = $this->createUserWithRole(Role::Mitglied);
        Route::get('/_testing/membership-context', fn () => response()->json(['role' => auth()->user()->role()?->value]));
        $this->actingAs($admin)->get('/_testing/membership-context')->assertJson(['role' => Role::Admin->value]);
        DB::table('team_user')->where('user_id', $admin->id)->update(['role' => Role::Vorstand->value]);
        $this->get('/_testing/membership-context')->assertJson(['role' => Role::Vorstand->value]);
        $this->actingAs($member)->get('/_testing/membership-context')->assertJson(['role' => Role::Mitglied->value]);
    }

    #[TestWith(['POST'])]
    #[TestWith(['PATCH'])]
    #[TestWith(['DELETE'])]
    public function test_unsafe_http_requests_always_read_fresh_membership(string $method): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        Route::match([$method], '/_testing/membership-write', function () use ($user) {
            $before = $user->role();
            DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);

            return response()->json(['before' => $before->value, 'after' => $user->role()->value]);
        });
        $this->actingAs($user)->call($method, '/_testing/membership-write')->assertOk()
            ->assertJson(['before' => Role::Admin->value, 'after' => Role::Mitglied->value]);
    }

    public function test_exception_resets_http_context_and_restores_memoization(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $context = app(MembershipContext::class);
        try {
            (new ResetMembershipContext)->handle(Request::create('/', 'POST'), function () use ($user) {
                $this->assertSame(Role::Admin, $user->role());
                DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
                throw new RuntimeException('expected');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('expected', $exception->getMessage());
        }
        $snapshot = $context->forUserInTeam($user, Team::membersTeam());
        $this->assertSame(Role::Mitglied, $snapshot->role);
        $this->assertSame($snapshot, $context->forUserInTeam($user, Team::membersTeam()));
    }

    public function test_livewire_followup_request_observes_revoked_privilege(): void
    {
        $user = $this->actingMember(Role::Admin);
        $component = Livewire::test(MitgliederIndex::class);
        $this->assertTrue($component->instance()->canViewDetails);
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        $component->call('sort', 'name')->assertOk();
        $this->assertFalse($component->instance()->canViewDetails);
    }

    public function test_job_boundaries_discard_context_on_success_and_failure(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('uuid')->andReturn('membership-test');
        $job->shouldReceive('resolveName')->andReturn('MembershipTestJob');
        $job->shouldReceive('payload')->andReturn([]);
        foreach ([new JobProcessing('sync', $job), new JobProcessed('sync', $job), new JobExceptionOccurred('sync', $job, new RuntimeException('expected'))] as $event) {
            $this->assertSame(Role::Admin, $user->role());
            DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
            Event::dispatch($event);
            $this->assertSame(Role::Mitglied, $user->role());
            Team::membersTeam()->users()->updateExistingPivot($user->id, ['role' => Role::Admin->value]);
        }
    }

    public function test_commands_reset_context_before_and_after_execution(): void
    {
        $kernel = app(Kernel::class);
        // Laravel deliberately disables Symfony command events in unit tests.
        $kernel->rerouteSymfonyCommandEvents()->setArtisan(null);
        $user = $this->createUserWithRole(Role::Admin);
        $this->assertSame(Role::Admin, $user->role());
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        $testCase = $this;
        $kernel->registerCommand(new ClosureCommand('membership-context:probe', function () use ($user, $testCase): void {
            $testCase->assertSame(Role::Mitglied, $user->role());
            DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Vorstand->value]);
        }));
        $this->artisan('membership-context:probe')->assertSuccessful();
        $this->assertSame(Role::Vorstand, $user->role());
    }
}

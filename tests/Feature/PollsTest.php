<?php

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Livewire\Umfragen\UmfrageVerwaltung;
use App\Livewire\Umfragen\UmfrageVote;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Services\Polls\PollVotingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PollsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUserWithRole;

    public function test_admin_can_access_poll_management_route(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->actingAs($admin)
            ->get(route('admin.umfragen.index'))
            ->assertOk();
    }

    public function test_member_cannot_access_poll_management_route(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->actingAs($member)
            ->get(route('admin.umfragen.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_poll_management_route(): void
    {
        $this->get(route('admin.umfragen.index'))
            ->assertRedirect(route('login'));
    }

    public function test_only_one_poll_can_be_active(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $pollA = Poll::query()->create([
            'question' => 'A?',
            'menu_label' => 'Umfrage A',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'activated_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $pollA->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        $pollB = Poll::query()->create([
            'question' => 'B?',
            'menu_label' => 'Umfrage B',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Draft,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'created_by_user_id' => $admin->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $pollB->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(UmfrageVerwaltung::class)
            ->call('selectPoll', $pollB->id)
            ->call('activate')
            ->assertHasErrors(['poll']);
    }

    public function test_member_can_vote_once_in_internal_poll(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $poll = Poll::query()->create([
            'question' => 'Intern?',
            'menu_label' => 'Interne Umfrage',
            'visibility' => PollVisibility::Internal,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $member->id,
        ]);

        $option = PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Ja',
            'sort_order' => 0,
        ]);

        Livewire::actingAs($member)
            ->test(UmfrageVote::class)
            ->set('selectedOptionId', $option->id)
            ->call('submit')
            ->assertSet('hasVoted', true);

        Livewire::actingAs($member)
            ->test(UmfrageVote::class)
            ->assertSet('hasVoted', true);
    }

    public function test_guest_can_vote_once_in_public_poll_per_ip_hash(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Public?',
            'menu_label' => 'Öffentliche Umfrage',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        $option = PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        $service = app(PollVotingService::class);
        $ipHash = hash_hmac('sha256', '203.0.113.5', (string) config('app.key'));

        $service->vote($poll, $option, null, $ipHash);

        try {
            $service->vote($poll, $option, null, $ipHash);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Von dieser IP wurde bereits abgestimmt', $e->errors()['poll'][0] ?? '');
        }
    }

    public function test_voting_is_blocked_before_start_date(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Startet später?',
            'menu_label' => 'Spätere Umfrage',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'activated_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        Livewire::test(UmfrageVote::class)
            ->assertSet('canVote', false)
            ->assertSee('noch nicht gestartet');
    }
}

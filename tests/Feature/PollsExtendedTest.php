<?php

namespace Tests\Feature;

use App\Console\Commands\ArchiveEndedPolls;
use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Livewire\Umfragen\UmfrageVerwaltung;
use App\Livewire\Umfragen\UmfrageVote;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use App\Services\Polls\PollVotingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PollsExtendedTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUserWithRole;

    public function test_umfrage_vote_shows_message_when_no_active_poll(): void
    {
        Livewire::test(UmfrageVote::class)
            ->assertSee('Aktuell läuft keine Umfrage.');
    }

    public function test_voting_is_blocked_after_end_date(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Schon vorbei?',
            'menu_label' => 'Beendete Umfrage',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'activated_at' => now()->subDays(2),
            'created_by_user_id' => $creator->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Ja',
            'sort_order' => 0,
        ]);

        Livewire::test(UmfrageVote::class)
            ->assertSet('canVote', false)
            ->assertSee('bereits beendet');
    }

    public function test_internal_poll_requires_login_for_voting(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Intern?',
            'menu_label' => 'Interne Umfrage',
            'visibility' => PollVisibility::Internal,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Ja',
            'sort_order' => 0,
        ]);

        Livewire::test(UmfrageVote::class)
            ->assertSet('canVote', false)
            ->assertSee('Bitte logge dich ein');
    }

    public function test_internal_poll_blocks_non_member_even_when_logged_in(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Nur Mitglieder?',
            'menu_label' => 'Mitgliederumfrage',
            'visibility' => PollVisibility::Internal,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Ja',
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UmfrageVote::class)
            ->assertSet('canVote', false)
            ->assertSee('nur für Vereinsmitglieder');
    }

    public function test_navigation_menu_shows_public_poll_for_guests_only_within_window(): void
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

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        $html = view('navigation-menu')->render();
        $this->assertStringContainsString('Öffentliche Umfrage', $html);
        $this->assertStringContainsString(route('umfrage.aktuell'), $html);

        $poll->update([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $html2 = view('navigation-menu')->render();
        $this->assertStringNotContainsString('Öffentliche Umfrage', $html2);
    }

    public function test_navigation_menu_shows_active_poll_for_authenticated_users_only_within_window(): void
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

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Ja',
            'sort_order' => 0,
        ]);

        $html = $this->actingAs($member)->get(route('dashboard'))->getContent();
        $this->assertStringContainsString('Interne Umfrage', $html);

        $poll->update([
            'ends_at' => now()->subMinute(),
        ]);

        $html2 = $this->actingAs($member)->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString('Interne Umfrage', $html2);
    }

    public function test_archive_command_archives_ended_active_polls(): void
    {
        $creator = User::factory()->create();

        $ended = Poll::query()->create([
            'question' => 'Beendet?',
            'menu_label' => 'Archiv',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'activated_at' => now()->subDays(3),
            'created_by_user_id' => $creator->id,
        ]);

        $running = Poll::query()->create([
            'question' => 'Läuft?',
            'menu_label' => 'Running',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now()->subHour(),
            'created_by_user_id' => $creator->id,
        ]);

        $this->artisan('polls:archive-ended')
            ->assertExitCode(0);

        $ended->refresh();
        $running->refresh();

        $this->assertSame(PollStatus::Archived, $ended->status);
        $this->assertNotNull($ended->archived_at);
        $this->assertSame(PollStatus::Active, $running->status);
    }

    public function test_admin_can_create_poll_with_13_options_and_cannot_add_more(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $component = Livewire::actingAs($admin)
            ->test(UmfrageVerwaltung::class);

        for ($i = 0; $i < 20; $i++) {
            $component->call('addOption');
        }

        $component->assertCount('options', 13);

        $options = [];
        for ($i = 0; $i < 13; $i++) {
            $options[] = [
                'label' => 'Option ' . ($i + 1),
                'image_url' => null,
                'link_url' => null,
            ];
        }

        $component
            ->set('question', 'Frage?')
            ->set('menuLabel', 'Umfrage Test')
            ->set('visibility', PollVisibility::Public->value)
            ->set('startsAt', now()->subMinute()->format('Y-m-d\\TH:i'))
            ->set('endsAt', now()->addDay()->format('Y-m-d\\TH:i'))
            ->set('options', $options)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('poll_options', 13);
    }

    public function test_admin_cannot_remove_option_that_already_has_votes(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $poll = Poll::query()->create([
            'question' => 'Q?',
            'menu_label' => 'Label',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Draft,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'created_by_user_id' => $admin->id,
        ]);

        $optionA = PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'A',
            'sort_order' => 0,
        ]);

        $optionB = PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'B',
            'sort_order' => 1,
        ]);

        PollVote::query()->create([
            'poll_id' => $poll->id,
            'poll_option_id' => $optionB->id,
            'user_id' => null,
            'ip_hash' => hash_hmac('sha256', '203.0.113.10', (string) config('app.key')),
            'voter_type' => 'guest',
        ]);

        Livewire::actingAs($admin)
            ->test(UmfrageVerwaltung::class)
            ->call('selectPoll', $poll->id)
            ->set('options', [
                ['id' => $optionA->id, 'label' => 'A', 'image_url' => null, 'link_url' => null],
            ])
            ->call('save')
            ->assertHasErrors(['options']);
    }

    public function test_activation_fails_for_already_ended_poll(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $poll = Poll::query()->create([
            'question' => 'Ende?',
            'menu_label' => 'Ende',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Draft,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'created_by_user_id' => $admin->id,
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'label' => 'Option',
            'sort_order' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(UmfrageVerwaltung::class)
            ->call('selectPoll', $poll->id)
            ->call('activate')
            ->assertHasErrors(['poll']);
    }

    public function test_rate_limiter_blocks_repeated_public_vote_attempts(): void
    {
        $creator = User::factory()->create();

        $poll = Poll::query()->create([
            'question' => 'Rate?',
            'menu_label' => 'Rate',
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

        $ipHash = hash_hmac('sha256', '203.0.113.77', (string) config('app.key'));
        $rateKey = 'poll-vote:' . $poll->id . ':' . $ipHash;
        RateLimiter::clear($rateKey);

        // First vote succeeds.
        app(\App\Services\Polls\PollVotingService::class)->vote($poll, $option, null, $ipHash);

        // Hammer the endpoint until rate limiter triggers (keep this aligned with PollVotingService defaults).
        $last = null;
        for ($i = 0; $i < (PollVotingService::PUBLIC_VOTE_RATE_LIMIT_MAX_ATTEMPTS + 5); $i++) {
            try {
                app(\App\Services\Polls\PollVotingService::class)->vote($poll, $option, null, $ipHash);
            } catch (ValidationException $e) {
                $last = $e;
            }
        }

        $this->assertInstanceOf(ValidationException::class, $last);
        $errors = $last->errors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Zu viele Versuche', $errors['poll'][0] ?? '');
    }

    public function test_active_poll_without_options_is_not_votable(): void
    {
        $creator = User::factory()->create();

        Poll::query()->create([
            'question' => 'Ohne Optionen? ',
            'menu_label' => 'Ohne Optionen',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        Livewire::test(UmfrageVote::class)
            ->assertSet('canVote', false)
            ->assertSee('keine Antwortmöglichkeiten');
    }

    public function test_admin_results_chart_data_is_populated(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $poll = Poll::query()->create([
            'question' => 'Ergebnis?',
            'menu_label' => 'Ergebnis',
            'visibility' => PollVisibility::Public,
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        $a = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'A', 'sort_order' => 0]);
        $b = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'B', 'sort_order' => 1]);

        PollVote::query()->create([
            'poll_id' => $poll->id,
            'poll_option_id' => $a->id,
            'user_id' => null,
            'ip_hash' => hash_hmac('sha256', '203.0.113.1', (string) config('app.key')),
            'voter_type' => 'guest',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        PollVote::query()->create([
            'poll_id' => $poll->id,
            'poll_option_id' => $b->id,
            'user_id' => null,
            'ip_hash' => hash_hmac('sha256', '203.0.113.2', (string) config('app.key')),
            'voter_type' => 'guest',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(UmfrageVerwaltung::class)
            ->call('selectPoll', $poll->id)
            ->assertSet('chartData.totals.totalVotes', 2)
            ->assertSet('chartData.options.total.0', 1)
            ->assertSet('chartData.options.total.1', 1);
    }

}

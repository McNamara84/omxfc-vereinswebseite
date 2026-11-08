<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('umfrage vote shows message when no active poll', function (): void {
    Livewire::test(UmfrageVote::class)->assertSee('Aktuell läuft keine Umfrage.');
});

test('voting is blocked after end date', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Schon vorbei?', 'menu_label' => 'Beendete Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'activated_at' => now()->subDays(2), 'created_by_user_id' => $creator->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Ja', 'sort_order' => 0]);
    Livewire::test(UmfrageVote::class)->assertSet('canVote', false)->assertSee('bereits beendet');
});

test('internal poll requires login for voting', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Intern?', 'menu_label' => 'Interne Umfrage', 'visibility' => PollVisibility::Internal, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Ja', 'sort_order' => 0]);
    Livewire::test(UmfrageVote::class)->assertSet('canVote', false)->assertSee('Bitte logge dich ein');
});

test('internal poll blocks non member even when logged in', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Nur Mitglieder?', 'menu_label' => 'Mitgliederumfrage', 'visibility' => PollVisibility::Internal, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Ja', 'sort_order' => 0]);
    $user = $this->createUserWithRole(Role::Anwaerter);
    Livewire::actingAs($user)->test(UmfrageVote::class)->assertSet('canVote', false)->assertSee('nur für Vereinsmitglieder');
});

test('navigation menu shows public poll for guests only within window', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Public?', 'menu_label' => 'Öffentliche Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option', 'sort_order' => 0]);
    $html = view('navigation-menu')->render();
    expect($html)->toContain('Öffentliche Umfrage')
        ->toContain(route('umfrage.aktuell'));
    $poll->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    $html2 = view('navigation-menu')->render();
    expect($html2)->not->toContain('Öffentliche Umfrage');
});

test('navigation menu shows active poll for authenticated users only within window', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $poll = Poll::query()->create(['question' => 'Intern?', 'menu_label' => 'Interne Umfrage', 'visibility' => PollVisibility::Internal, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $member->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Ja', 'sort_order' => 0]);
    $html = $this->actingAs($member)->get(route('dashboard'))->getContent();
    expect($html)->toContain('Interne Umfrage');
    $poll->update(['ends_at' => now()->subMinute()]);
    $html2 = $this->actingAs($member)->get(route('dashboard'))->getContent();
    expect($html2)->not->toContain('Interne Umfrage');
});

test('archive command archives ended active polls', function (): void {
    $creator = User::factory()->create();
    $ended = Poll::query()->create(['question' => 'Beendet?', 'menu_label' => 'Archiv', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay(), 'activated_at' => now()->subDays(3), 'created_by_user_id' => $creator->id]);
    $running = Poll::query()->create(['question' => 'Läuft?', 'menu_label' => 'Running', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now()->subHour(), 'created_by_user_id' => $creator->id]);
    $this->artisan('polls:archive-ended')->assertExitCode(0);
    $ended->refresh();
    $running->refresh();
    expect($ended->status)->toBe(PollStatus::Archived)
        ->and($ended->archived_at)->not->toBeNull()
        ->and($running->status)->toBe(PollStatus::Active);
});

test('admin can create poll with 13 options and cannot add more', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $component = Livewire::actingAs($admin)->test(UmfrageVerwaltung::class);
    for ($i = 0; $i < 20; $i++) {
        $component->call('addOption');
    }
    $component->assertCount('options', 13);
    $options = [];
    for ($i = 0; $i < 13; $i++) {
        $options[] = ['label' => 'Option '.($i + 1), 'image_url' => null, 'link_url' => null];
    }
    $component->set('question', 'Frage?')->set('menuLabel', 'Umfrage Test')->set('visibility', PollVisibility::Public->value)->set('startsAt', now()->subMinute()->format('Y-m-d\TH:i'))->set('endsAt', now()->addDay()->format('Y-m-d\TH:i'))->set('options', $options)->call('save')->assertHasNoErrors();
    $this->assertDatabaseCount('poll_options', 13);
});

test('admin cannot remove option that already has votes', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $poll = Poll::query()->create(['question' => 'Q?', 'menu_label' => 'Label', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'created_by_user_id' => $admin->id]);
    $optionA = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'A', 'sort_order' => 0]);
    $optionB = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'B', 'sort_order' => 1]);
    PollVote::query()->create(['poll_id' => $poll->id, 'poll_option_id' => $optionB->id, 'user_id' => null, 'ip_hash' => hash_hmac('sha256', '203.0.113.10', (string) config('app.key')), 'voter_type' => 'guest']);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->set('options', [['id' => $optionA->id, 'label' => 'A', 'image_url' => null, 'link_url' => null]])->call('save')->assertHasErrors(['options']);
});

test('activation fails for already ended poll', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $poll = Poll::query()->create(['question' => 'Ende?', 'menu_label' => 'Ende', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'created_by_user_id' => $admin->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option', 'sort_order' => 0]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->call('activate')->assertHasErrors(['poll']);
});

test('rate limiter blocks repeated public vote attempts', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Rate?', 'menu_label' => 'Rate', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    $option = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option', 'sort_order' => 0]);
    $ipHash = hash_hmac('sha256', '203.0.113.77', (string) config('app.key'));
    $rateKey = 'poll-vote:'.$poll->id.':'.$ipHash;
    RateLimiter::clear($rateKey);
    // Simulate repeated submissions without persisting a vote (rollback).
    // This keeps the "already voted" check from short-circuiting the rate limiter.
    $service = app(PollVotingService::class);
    $last = null;
    for ($i = 0; $i < PollVotingService::PUBLIC_VOTE_RATE_LIMIT_MAX_ATTEMPTS + 5; $i++) {
        DB::beginTransaction();
        try {
            $service->vote($poll, $option, null, $ipHash);
        } catch (ValidationException $e) {
            $last = $e;
        } finally {
            DB::rollBack();
        }
        if ($last && str_contains($last->errors()['poll'][0] ?? '', 'Zu viele Versuche')) {
            break;
        }
    }
    expect($last)->not->toBeNull()
        ->toBeInstanceOf(ValidationException::class);
    $errors = $last->errors();
    expect($errors)->not->toBeEmpty()
        ->and($errors['poll'][0] ?? '')->toContain('Zu viele Versuche');
});

test('active poll without options is not votable', function (): void {
    $creator = User::factory()->create();
    Poll::query()->create(['question' => 'Ohne Optionen?', 'menu_label' => 'Ohne Optionen', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    Livewire::test(UmfrageVote::class)->assertSet('canVote', false)->assertSee('keine Antwortmöglichkeiten');
});

test('admin results chart data is populated', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $poll = Poll::query()->create(['question' => 'Ergebnis?', 'menu_label' => 'Ergebnis', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $admin->id]);
    $a = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'A', 'sort_order' => 0]);
    $b = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'B', 'sort_order' => 1]);
    PollVote::query()->create(['poll_id' => $poll->id, 'poll_option_id' => $a->id, 'user_id' => null, 'ip_hash' => hash_hmac('sha256', '203.0.113.1', (string) config('app.key')), 'voter_type' => 'guest', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    PollVote::query()->create(['poll_id' => $poll->id, 'poll_option_id' => $b->id, 'user_id' => null, 'ip_hash' => hash_hmac('sha256', '203.0.113.2', (string) config('app.key')), 'voter_type' => 'guest', 'created_at' => now(), 'updated_at' => now()]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->assertSet('chartData.totals.totalVotes', 2)->assertSet('chartData.options.total.0', 1)->assertSet('chartData.options.total.1', 1);
});

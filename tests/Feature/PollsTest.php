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
use App\Models\User;
use App\Services\Polls\PollVotingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('admin can access poll management route', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin)->get(route('admin.umfragen.index'))->assertOk();
});

test('member cannot access poll management route', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($member)->get(route('admin.umfragen.index'))->assertForbidden();
});

test('guest is redirected from poll management route', function (): void {
    $this->get(route('admin.umfragen.index'))->assertRedirect(route('login'));
});

test('only one poll can be active', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $pollA = Poll::query()->create(['question' => 'A?', 'menu_label' => 'Umfrage A', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'activated_at' => now(), 'created_by_user_id' => $admin->id]);
    PollOption::query()->create(['poll_id' => $pollA->id, 'label' => 'Option', 'sort_order' => 0]);
    $pollB = Poll::query()->create(['question' => 'B?', 'menu_label' => 'Umfrage B', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'created_by_user_id' => $admin->id]);
    PollOption::query()->create(['poll_id' => $pollB->id, 'label' => 'Option', 'sort_order' => 0]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $pollB->id)->call('activate')->assertHasErrors(['poll']);
});

test('member can vote once in internal poll', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $poll = Poll::query()->create(['question' => 'Intern?', 'menu_label' => 'Interne Umfrage', 'visibility' => PollVisibility::Internal, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $member->id]);
    $option = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Ja', 'sort_order' => 0]);
    Livewire::actingAs($member)->test(UmfrageVote::class)->set('selectedOptionId', $option->id)->call('submit')->assertSet('hasVoted', true);
    Livewire::actingAs($member)->test(UmfrageVote::class)->assertSet('hasVoted', true);
});

test('guest can vote once in public poll per ip hash', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Public?', 'menu_label' => 'Öffentliche Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    $option = PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option', 'sort_order' => 0]);
    $service = app(PollVotingService::class);
    $ipHash = hash_hmac('sha256', '203.0.113.5', (string) config('app.key'));
    $service->vote($poll, $option, null, $ipHash);
    try {
        $service->vote($poll, $option, null, $ipHash);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors()['poll'][0] ?? '')->toContain(PollVotingService::ERROR_ALREADY_VOTED_IP);
    }
});

test('vote options use selectable daisyui cards with direct radio controls', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Welche Option?', 'menu_label' => 'Card-Test', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    $options = collect(['Option A', 'Option B'])->map(fn (string $label, int $index): PollOption => PollOption::query()->create(['poll_id' => $poll->id, 'label' => $label, 'sort_order' => $index]));
    $crawler = new Crawler(Livewire::test(UmfrageVote::class)->html());
    $cards = $crawler->filter('label.card.card-border[data-testid^="poll-option-card-"]');
    $radios = $crawler->filter('label.card > input.radio[type="radio"][name="poll-option"]');
    expect($cards)->toHaveCount(2)
        ->and($radios)->toHaveCount(2)
        ->and($radios->each(fn (Crawler $radio): ?string => $radio->attr('value')))->toBe($options->map(fn (PollOption $option): string => (string) $option->id)->all());
    $cards->each(function (Crawler $card): void {
        expect($card->children()->filter('input.radio[type="radio"]'))->toHaveCount(1)
            ->and($card->children()->filter('.card-body'))->toHaveCount(1);
    });
});

test('voting is blocked before start date', function (): void {
    $creator = User::factory()->create();
    $poll = Poll::query()->create(['question' => 'Startet später?', 'menu_label' => 'Spätere Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Active, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'activated_at' => now(), 'created_by_user_id' => $creator->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option', 'sort_order' => 0]);
    Livewire::test(UmfrageVote::class)->assertSet('canVote', false)->assertSee('noch nicht gestartet');
});

test('new poll does not dispatch results event', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('newPoll')->assertNotDispatched('poll-results-updated');
});

test('select poll without options does not dispatch results event', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    // Poll ohne Optionen erstellen
    $poll = Poll::query()->create(['question' => 'Leere Umfrage?', 'menu_label' => 'Leere Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'created_by_user_id' => $admin->id]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->assertNotDispatched('poll-results-updated');
});

test('select poll with options dispatches results event', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $poll = Poll::query()->create(['question' => 'Umfrage mit Optionen?', 'menu_label' => 'Test Umfrage', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'created_by_user_id' => $admin->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option A', 'sort_order' => 0]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option B', 'sort_order' => 1]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->assertDispatched('poll-results-updated');
});

test('admin page loads without error when no polls exist', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    // Sicherstellen, dass keine Polls existieren
    Poll::query()->delete();
    $this->actingAs($admin)->get(route('admin.umfragen.index'))->assertOk()->assertSee('Umfrage verwalten');
});

test('chart data is empty array when no poll selected', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $component = Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('newPoll');
    $this->assertArraysAreIdentical([], $component->get('chartData'));
});

test('poll with options but no votes shows hint text and no charts', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    // Poll mit Optionen aber ohne Stimmen erstellen
    $poll = Poll::query()->create(['question' => 'Umfrage ohne Stimmen?', 'menu_label' => 'Test ohne Votes', 'visibility' => PollVisibility::Public, 'status' => PollStatus::Draft, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'created_by_user_id' => $admin->id]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option A', 'sort_order' => 0]);
    PollOption::query()->create(['poll_id' => $poll->id, 'label' => 'Option B', 'sort_order' => 1]);
    Livewire::actingAs($admin)->test(UmfrageVerwaltung::class)->call('selectPoll', $poll->id)->assertSee('Noch keine Stimmen abgegeben')->assertDontSeeHtml('id="poll-options-chart"')->assertDontSeeHtml('id="poll-timeline-chart"')->assertDontSeeHtml('id="poll-segment-chart"')->assertSee('Option A')->assertSee('Option B');
});

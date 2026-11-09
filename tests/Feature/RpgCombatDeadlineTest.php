<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendRpgCombatMail;
use App\Mail\RpgCombatMail;
use App\Models\RpgCombatDelivery;
use App\Models\RpgCombatMilestone;
use App\Services\RpgCombat\CombatService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCombatFixtures::class);
dataset('RpgCombatDeadlineTest.decisionMailDeadlines', static fn (): array => ['player just before deadline' => [false, -1, true], 'player at deadline' => [false, 0, false], 'player after delayed scheduler' => [false, 3600, false], 'leader just before deadline' => [true, -1, true], 'leader at deadline' => [true, 0, false], 'leader after delayed scheduler' => [true, 3600, false]]);
dataset('RpgCombatDeadlineTest.dstInstants', static fn (): array => [['2026-03-28T12:00:00+01:00'], ['2026-10-24T12:00:00+02:00'], ['2026-10-25T02:30:00+02:00'], ['2026-10-25T02:30:00+01:00']]);
beforeEach(function (): void {
    $this->combatFixtures();
    Mail::fake();
});
test('due decision runs once and next gets a fresh day', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $this->travelTo($decision->due_at);
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($decision->fresh()->automatic)->toBeTrue();
    $next = $combat->decisions()->where('status', 'pending')->firstOrFail();
    expect($next->due_at->timestamp)->toBe(now()->addHours(24)->timestamp);
    $calls = $this->combatDice->calls;
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($this->combatDice->calls)->toBe($calls)
        ->and($combat->fresh()->state['seconds'])->toBe(0);
});
test('late player input uses timeout even before scheduler runs', function (): void {
    $combat = $this->activeCombat();
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $this->travelTo($decision->due_at);
    $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['kind' => 'wait'])->assertRedirect();
    expect($decision->fresh()->automatic)->toBeTrue()
        ->and($decision->fresh()->response['kind'])->toBe('attack');
    $this->assertDatabaseHas('rpg_combat_events', ['rpg_combat_id' => $combat->id, 'origin' => 'timeout']);
});
test('invitation expires without start or dice', function (): void {
    $combat = $this->combat(false);
    $this->travelTo($combat->expires_at);
    $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertRedirect();
    expect($combat->fresh()->status)->toBe('expired')
        ->and($combat->fresh()->state)->toBeNull()
        ->and($this->combatDice->calls)->toBe(0);
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
});
test('ruling pauses other decisions preserving remaining time', function (): void {
    $payload = $this->progressionPayload();
    $payload['equipment']['items'] = [['id' => 'schwert', 'quantity' => 1]];
    $this->character->update(['payload' => $payload]);
    $combat = $this->combat();
    $second = $combat->decisions()->where('side', 2)->firstOrFail();
    $this->travel(3)->hours();
    $combat = $this->decision($combat, 'prepare', 1, ['weapons' => ['schwert:1'], 'shield' => false, 'skill' => 'Nahkampf']);
    expect($second->fresh()->status)->toBe('paused');
    $remaining = $second->fresh()->remaining_seconds;
    $this->travel(24)->hours();
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($second->fresh()->status)->toBe('pending')
        ->and($second->fresh()->due_at->timestamp)->toBe(now()->addSeconds($remaining)->timestamp)
        ->and($second->fresh()->automatic)->toBeFalse()
        ->and($combat->fresh()->state['rules']['hands'])->toBe('catalog');
});
test('membership removal and character deletion release both characters', function (): void {
    $combat = $this->combat();
    $this->rpgTeam->users()->detach($this->player);
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($combat->fresh()->result)->toBe('membership_changed');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect($combat->decisions()->whereIn('status', ['pending', 'paused'])->count())->toBe(0);
    $this->rpgTeam->users()->attach($this->player, ['role' => 'mitglied']);
    $next = $this->combat();
    $this->character->delete();
    expect($next->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    expect($next->fresh()->participants[0]->rpg_character_id)->toBeNull();
});
test('mail outbox deduplicates and cancels stale notifications', function (): void {
    $combat = $this->combat(false);
    $delivery = RpgCombatDelivery::firstOrFail();
    (new SendRpgCombatMail($delivery->id))->handle();
    (new SendRpgCombatMail($delivery->id))->handle();
    Mail::assertSent(RpgCombatMail::class, 1);
    expect($delivery->fresh()->status)->toBe('sent');
    app(CombatService::class)->command($this->opponent, $combat->id, 'accept', (string) Str::uuid());
    $this->rpgTeam->users()->detach($this->player);
    foreach (RpgCombatDelivery::where('recipient_id', $this->player->id)->get() as $stale) {
        (new SendRpgCombatMail($stale->id))->handle();
        expect($stale->fresh()->status)->toBe('cancelled');
    }
    Mail::assertSent(RpgCombatMail::class, 1);
    expect((new RpgCombatMail($delivery))->render())->toContain('Übungskampf');
});
test('mail dispatch occurs via queue after committed state', function (): void {
    Queue::fake();
    $combat = $this->combat();
    $this->artisan('rpg:process-combats')->assertSuccessful();
    Queue::assertPushed(SendRpgCombatMail::class);
    expect(RpgCombatDelivery::count())->toBe(5)
        ->and($combat->fresh()->status)->toBe('preparing');
});
test('decision mail requires a future deadline even before scheduler runs', function (bool $ruling, int $secondsAfterDeadline, bool $shouldSend): void {
    $combat = $this->activeCombat();
    if ($ruling) {
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Staub aufwirbeln']);
    }
    $decision = $combat->decisions()->where('status', 'pending')->sole();
    $delivery = RpgCombatDelivery::where('rpg_combat_decision_id', $decision->id)->sole();
    $recipient = $ruling ? $this->leader : $this->player;
    expect($delivery->recipient_id)->toBe($recipient->id);
    $this->travelTo($decision->due_at->addSeconds($secondsAfterDeadline));
    (new SendRpgCombatMail($delivery->id))->handle();
    (new SendRpgCombatMail($delivery->id))->handle();
    expect($decision->fresh()->status)->toBe('pending')
        ->and($delivery->fresh()->status)->toBe($shouldSend ? 'sent' : 'cancelled');
    if ($shouldSend) {
        Mail::assertSent(RpgCombatMail::class, 1);
        Mail::assertSent(RpgCombatMail::class, fn ($mail) => $mail->hasTo($recipient->email));
    } else {
        Mail::assertNothingSent();
        expect($delivery->fresh()->sent_at)->toBeNull();
    }
})->with('RpgCombatDeadlineTest.decisionMailDeadlines');
test('user deletion anonymizes public names', function (): void {
    $combat = $this->combat();
    $this->player->delete();
    expect($combat->fresh()->result)->toBe('membership_changed');
    foreach (RpgCombatMilestone::all() as $milestone) {
        expect($milestone->challenger_name)->toBe('Ehemaliges Mitglied');
    }
});
test('failed mail remains retryable and abandoned claim can be recovered', function (): void {
    $this->combat(false);
    $delivery = RpgCombatDelivery::firstOrFail();
    $originalMail = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Temporary SMTP failure'));
    try {
        (new SendRpgCombatMail($delivery->id))->handle();
        $this->fail('Expected mail failure');
    } catch (\RuntimeException $error) {
        expect($error->getMessage())->toBe('Temporary SMTP failure');
    }
    expect($delivery->fresh()->status)->toBe('pending');
    Mail::swap($originalMail);
    $delivery->update(['status' => 'processing', 'claimed_at' => now()->subMinutes(16)]);
    (new SendRpgCombatMail($delivery->id))->handle();
    Mail::assertSent(RpgCombatMail::class, 1);
    expect($delivery->fresh()->status)->toBe('sent')
        ->and((new SendRpgCombatMail($delivery->id))->backoff()[0])->toBeGreaterThan(0);
});
test('disabled feature never advances decisions', function (): void {
    $combat = $this->combat();
    $this->travel(25)->hours();
    config(['rpg-combat.enabled' => false]);
    $this->artisan('rpg:process-combats')->assertSuccessful();
    expect($combat->decisions()->where('status', 'pending')->count())->toBe(2);
    $this->actingAs($this->player)->get(route('rpg.combats.index'))->assertForbidden();
});
test('html form numbers booleans and empty loadout are normalized', function (): void {
    $combat = $this->combat();
    $decision = $combat->decisions()->where('side', 1)->firstOrFail();
    $this->actingAs($this->player)->post(route('rpg.combats.decide', [$combat, $decision->id]), ['shield' => '0', 'skill' => 'Nahkampf'])->assertRedirect();
    expect($decision->fresh()->response['weapons'])->toBe([]);
    $combat = $this->decision($combat, 'prepare', 2, ['shield' => false, 'skill' => 'Nahkampf', 'weapons' => []]);
    $this->combatDice->values = [6, 1];
    foreach ([1, 2] as $side) {
        $combat = $this->decision($combat, 'initiative', $side);
    }
    $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
    $this->post(route('rpg.combats.decide', [$combat, $decision->id]), ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'move' => '0', 'mode' => '0', 'aim' => '0', 'fire' => 'E'])->assertRedirect();
    expect($decision->fresh()->response['move'])->toBe(0);
});
test('deadlines are exactly twenty four hours across clock changes', function (string $instant): void {
    $this->travelTo(CarbonImmutable::parse($instant));
    $combat = $this->combat();
    $decision = $combat->decisions()->firstOrFail();
    expect($decision->due_at->timestamp - $decision->opened_at->timestamp)->toBe(86400)
        ->and($decision->due_at->timezoneName)->toBe('UTC')
        ->and($decision->getRawOriginal('due_at'))->toBe($decision->due_at->format('Y-m-d H:i:s'));
    $this->travelTo($decision->due_at->subSecond());
    app(CombatService::class)->decide(null, $combat->id, $decision->id);
    expect($decision->fresh()->status)->toBe('pending');
    $this->travelTo($decision->due_at);
    app(CombatService::class)->decide(null, $combat->id, $decision->id);
    expect($decision->fresh()->automatic)->toBeTrue();
})->with('RpgCombatDeadlineTest.dstInstants');

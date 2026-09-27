<?php

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgCombatDeadlineTest extends TestCase
{
    use RefreshDatabase,RpgCombatFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatFixtures();
        Mail::fake();
    }

    public function test_due_decision_runs_once_and_next_gets_a_fresh_day(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $this->travelTo($decision->due_at);
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertTrue($decision->fresh()->automatic);
        $next = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $this->assertSame(now()->addHours(24)->timestamp, $next->due_at->timestamp);
        $calls = $this->combatDice->calls;
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame($calls, $this->combatDice->calls);
        $this->assertSame(0, $combat->fresh()->state['seconds']);
    }

    public function test_late_player_input_uses_timeout_even_before_scheduler_runs(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $this->travelTo($decision->due_at);
        $this->actingAs($this->player)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), ['kind' => 'wait'])->assertRedirect();
        $this->assertTrue($decision->fresh()->automatic);
        $this->assertSame('attack', $decision->fresh()->response['kind']);
        $this->assertDatabaseHas('rpg_combat_events', ['rpg_combat_id' => $combat->id, 'origin' => 'timeout']);
    }

    public function test_invitation_expires_without_start_or_dice(): void
    {
        $combat = $this->combat(false);
        $this->travelTo($combat->expires_at);
        $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('expired', $combat->fresh()->status);
        $this->assertNull($combat->fresh()->state);
        $this->assertSame(0, $this->combatDice->calls);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    }

    public function test_ruling_pauses_other_decisions_preserving_remaining_time(): void
    {
        $payload = $this->progressionPayload();
        $payload['equipment']['items'] = [['id' => 'schwert', 'quantity' => 1]];
        $this->character->update(['payload' => $payload]);
        $combat = $this->combat();
        $second = $combat->decisions()->where('side', 2)->firstOrFail();
        $this->travel(3)->hours();
        $combat = $this->decision($combat, 'prepare', 1, ['weapons' => ['schwert:1'], 'shield' => false, 'skill' => 'Nahkampf']);
        $this->assertSame('paused', $second->fresh()->status);
        $remaining = $second->fresh()->remaining_seconds;
        $this->travel(24)->hours();
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(now()->addSeconds($remaining)->timestamp, $second->fresh()->due_at->timestamp);
        $this->assertFalse($second->fresh()->automatic);
        $this->assertSame('catalog', $combat->fresh()->state['rules']['hands']);
    }

    public function test_membership_removal_and_character_deletion_release_both_characters(): void
    {
        $combat = $this->combat();
        $this->rpgTeam->users()->detach($this->player);
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame('membership_changed', $combat->fresh()->result);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertSame(0, $combat->decisions()->whereIn('status', ['pending', 'paused'])->count());
        $this->rpgTeam->users()->attach($this->player, ['role' => 'mitglied']);
        $next = $this->combat();
        $this->character->delete();
        $this->assertSame('completed', $next->fresh()->status);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertNull($next->fresh()->participants[0]->rpg_character_id);
    }

    public function test_mail_outbox_deduplicates_and_cancels_stale_notifications(): void
    {
        $combat = $this->combat(false);
        $delivery = RpgCombatDelivery::firstOrFail();
        (new SendRpgCombatMail($delivery->id))->handle();
        (new SendRpgCombatMail($delivery->id))->handle();
        Mail::assertSent(RpgCombatMail::class, 1);
        $this->assertSame('sent', $delivery->fresh()->status);
        app(CombatService::class)->command($this->opponent, $combat->id, 'accept', (string) Str::uuid());
        $this->rpgTeam->users()->detach($this->player);
        foreach (RpgCombatDelivery::where('recipient_id', $this->player->id)->get() as $stale) {
            (new SendRpgCombatMail($stale->id))->handle();
            $this->assertSame('cancelled', $stale->fresh()->status);
        }
        Mail::assertSent(RpgCombatMail::class, 1);
        $this->assertStringContainsString('Übungskampf', (new RpgCombatMail($delivery))->render());
    }

    public function test_mail_dispatch_occurs_via_queue_after_committed_state(): void
    {
        Queue::fake();
        $combat = $this->combat();
        $this->artisan('rpg:process-combats')->assertSuccessful();
        Queue::assertPushed(SendRpgCombatMail::class);
        $this->assertSame(5, RpgCombatDelivery::count());
        $this->assertSame('preparing', $combat->fresh()->status);
    }

    public function test_user_deletion_anonymizes_public_names(): void
    {
        $combat = $this->combat();
        $this->player->delete();
        $this->assertSame('membership_changed', $combat->fresh()->result);
        foreach (RpgCombatMilestone::all() as $milestone) {
            $this->assertSame('Ehemaliges Mitglied', $milestone->challenger_name);
        }
    }

    public function test_failed_mail_remains_retryable_and_abandoned_claim_can_be_recovered(): void
    {
        $this->combat(false);
        $delivery = RpgCombatDelivery::firstOrFail();
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Temporary SMTP failure'));
        try {
            (new SendRpgCombatMail($delivery->id))->handle();
            $this->fail('Expected mail failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Temporary SMTP failure', $error->getMessage());
        }
        $this->assertSame('pending', $delivery->fresh()->status);
        Mail::swap($originalMail);
        $delivery->update(['status' => 'processing', 'claimed_at' => now()->subMinutes(16)]);
        (new SendRpgCombatMail($delivery->id))->handle();
        Mail::assertSent(RpgCombatMail::class, 1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertGreaterThan(0, (new SendRpgCombatMail($delivery->id))->backoff()[0]);
    }

    public function test_disabled_feature_never_advances_decisions(): void
    {
        $combat = $this->combat();
        $this->travel(25)->hours();
        config(['rpg-combat.enabled' => false]);
        $this->artisan('rpg:process-combats')->assertSuccessful();
        $this->assertSame(2, $combat->decisions()->where('status', 'pending')->count());
        $this->actingAs($this->player)->get(route('rpg.combats.index'))->assertForbidden();
    }

    public function test_html_form_numbers_booleans_and_empty_loadout_are_normalized(): void
    {
        $combat = $this->combat();
        $decision = $combat->decisions()->where('side', 1)->firstOrFail();
        $this->actingAs($this->player)->post(route('rpg.combats.decide', [$combat, $decision->id]), ['shield' => '0', 'skill' => 'Nahkampf'])->assertRedirect();
        $this->assertSame([], $decision->fresh()->response['weapons']);
        $combat = $this->decision($combat, 'prepare', 2, ['shield' => false, 'skill' => 'Nahkampf', 'weapons' => []]);
        $this->combatDice->values = [6, 1];
        foreach ([1, 2] as $side) {
            $combat = $this->decision($combat, 'initiative', $side);
        }
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $this->post(route('rpg.combats.decide', [$combat, $decision->id]), ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'move' => '0', 'mode' => '0', 'aim' => '0', 'fire' => 'E'])->assertRedirect();
        $this->assertSame(0, $decision->fresh()->response['move']);
    }

    #[DataProvider('dstInstants')]
    public function test_deadlines_are_exactly_twenty_four_hours_across_clock_changes(string $instant): void
    {
        $this->travelTo(CarbonImmutable::parse($instant));
        $combat = $this->combat();
        $decision = $combat->decisions()->firstOrFail();
        $this->assertSame(86400, $decision->due_at->timestamp - $decision->opened_at->timestamp);
        $this->assertSame('UTC', $decision->due_at->timezoneName);
        $this->assertSame($decision->due_at->format('Y-m-d H:i:s'), $decision->getRawOriginal('due_at'));
        $this->travelTo($decision->due_at->subSecond());
        app(CombatService::class)->decide(null, $combat->id, $decision->id);
        $this->assertSame('pending', $decision->fresh()->status);
        $this->travelTo($decision->due_at);
        app(CombatService::class)->decide(null, $combat->id, $decision->id);
        $this->assertTrue($decision->fresh()->automatic);
    }

    public static function dstInstants(): array
    {
        return [['2026-03-28T12:00:00+01:00'], ['2026-10-24T12:00:00+02:00'], ['2026-10-25T02:30:00+02:00'], ['2026-10-25T02:30:00+01:00']];
    }
}

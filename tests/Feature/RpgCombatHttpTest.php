<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\RpgCombat;
use App\Models\RpgCombatMilestone;
use App\Services\RpgCombat\CombatQuery;
use App\Services\RpgCombat\CombatService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgCombatHttpTest extends TestCase
{
    use RefreshDatabase, RpgCombatFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatFixtures();
    }

    public function test_invitation_acceptance_and_pages_do_not_change_originals(): void
    {
        $input = $this->combatInput();
        $this->actingAs($this->player)->get(route('rpg.combats.create'))->assertOk()->assertSee('Startentfernung');
        $this->post(route('rpg.combats.store'), $input)->assertRedirect();
        $this->post(route('rpg.combats.store'), $input)->assertRedirect();
        $this->assertDatabaseCount('rpg_combats', 1);
        $combat = RpgCombat::firstOrFail();
        $this->assertNull($combat->state);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Arkon')->assertSee('Mira');
        $this->actingAs($this->opponent)->post(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('preparing', $combat->fresh()->status);
        $this->assertDatabaseCount('rpg_combat_character_locks', 2);
        $this->getJson(route('rpg.combats.show', $combat))->assertOk()->assertJsonStructure(['revision', 'html']);
        $this->get(route('rpg.combats.index'))->assertOk()->assertSee('Arkon');
        $this->assertSame($this->progressionPayload(), $this->character->fresh()->payload);
        $this->assertSame(0, $this->character->experienceBalance());
        $this->assertSame(2, Activity::where('subject_type', RpgCombatMilestone::class)->count());
    }

    public function test_complete_fight_and_retries_never_roll_twice(): void
    {
        $combat = $this->activeCombat();
        $this->combatDice->values = [6, 6, 1, 1, 6];
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $input = ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1'];
        $url = route('rpg.combats.decide', [$combat, $decision->id]);
        $this->actingAs($this->player)->postJson($url, $input)->assertRedirect();
        $calls = $this->combatDice->calls;
        $this->postJson($url, $input)->assertRedirect();
        $this->assertSame($calls, $this->combatDice->calls);
        $this->postJson($url, ['kind' => 'wait'])->assertConflict();
        $combat = $this->decision($combat, 'defense', 2, ['defense' => 'dodge']);
        $combat = $this->decision($combat, 'damage', 1);
        // Fist -1 plus critical +1 gives 6: severe, not lethal.
        $this->assertSame([3], $combat->state['actors'][2]['wounds']);
        $this->assertDatabaseCount('rpg_combat_character_locks', 2);
        $combat = app(CombatService::class)->command($this->opponent, $combat->id, 'surrender', (string) Str::uuid());
        $this->assertSame('completed', $combat->status);
        $this->assertSame(1, $combat->winner_side);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertSame(3, RpgCombatMilestone::count());
        $this->assertSame(range(1, $combat->events()->count()), $combat->events()->orderBy('sequence')->pluck('sequence')->all());
        $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Kampfprotokoll');
    }

    #[DataProvider('localCreationDates')]
    public function test_combat_listing_shows_the_berlin_creation_date(string $instant, string $expectedDate): void
    {
        $this->travelTo(CarbonImmutable::parse($instant));
        $combat = $this->combat(false);
        $this->assertSame('UTC', $combat->fresh()->created_at->timezoneName);
        $this->assertNotSame($expectedDate, $combat->fresh()->created_at->format('d.m.Y'));

        $this->actingAs($this->player)->get(route('rpg.combats.index'))
            ->assertOk()->assertSee('Herausforderung offen · '.$expectedDate);
    }

    public static function localCreationDates(): array
    {
        return [
            'winter after midnight' => ['2026-01-02T00:15:00+01:00', '02.01.2026'],
            'summer after midnight' => ['2026-07-02T00:15:00+02:00', '02.07.2026'],
        ];
    }

    public function test_access_is_limited_to_participants_and_current_leader(): void
    {
        $combat = $this->combat();
        $stranger = $this->progressionMember();
        $this->actingAs($stranger)->get(route('rpg.combats.show', $combat))->assertForbidden();
        $this->rpgTeam->users()->attach($stranger, ['role' => 'mitglied']);
        $this->getJson(route('rpg.combats.show', $combat))->assertForbidden();
        $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk();
        $decision = $combat->decisions()->firstOrFail();
        $this->postJson(route('rpg.combats.decide', [$combat, $decision->id]), [])->assertForbidden();
        $this->actingAs($this->opponent)->postJson(route('rpg.combats.decide', [$combat, $decision->id]), [])->assertForbidden();
        $this->rpgTeam->users()->detach($this->player);
        $this->actingAs($this->player)->getJson(route('rpg.combats.show', $combat))->assertForbidden();
    }

    public function test_forged_dice_and_foreign_decision_are_rejected(): void
    {
        $combat = $this->activeCombat();
        $decision = $combat->decisions()->where('status', 'pending')->firstOrFail();
        $url = route('rpg.combats.decide', [$combat, $decision->id]);
        $this->actingAs($this->player)->postJson($url, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'dice' => [6, 6]])->assertUnprocessable();
        $this->postJson($url, ['kind' => 'attack', 'weapon' => ['bad']])->assertUnprocessable();
        $this->postJson($url, ['kind' => 'attack', 'weapon' => 'faustschlag-tritt:1', 'move' => 99999])->assertUnprocessable();
        $this->assertSame('pending', $decision->fresh()->status);
        $this->assertSame(2, $this->combatDice->calls);
        $this->postJson(route('rpg.combats.decide', [$combat, 99999]), [])->assertNotFound();
    }

    public function test_stale_character_revision_requires_new_invitation(): void
    {
        $combat = $this->combat(false);
        $this->character->increment('revision');
        $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $combat), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    }

    public function test_private_dashboard_tasks_follow_ownership_and_current_leadership(): void
    {
        $query = app(CombatQuery::class);
        $combat = $this->activeCombat();
        $this->assertSame('Handlung wählen', $query->hints($this->player)[0]['tasks'][0]['label']);
        $this->assertNotNull($query->hints($this->player)[0]['tasks'][0]['due_at']);
        $this->assertSame([], $query->hints($this->opponent)[0]['tasks']);
        $this->assertSame([], $query->hints($this->leader));
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Staub aufwirbeln']);
        $this->assertSame('Regelfrage entscheiden', $query->hints($this->leader)[0]['tasks'][0]['label']);
        $member = $this->progressionMember();
        $this->rpgTeam->users()->attach($member, ['role' => 'mitglied']);
        $this->assertSame([], $query->hints($member));
        $this->rpgTeam->update(['user_id' => $member->id]);
        $this->assertSame([], $query->hints($this->leader));
        $this->assertSame('Regelfrage entscheiden', $query->hints($member)[0]['tasks'][0]['label']);
        $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('Regelfrage entscheiden')->assertSee('bis ');
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $this->assertNotContains('Regelfrage entscheiden', array_column($query->hints($this->player)[0]['tasks'], 'label'));
    }

    public function test_invitation_dashboard_displays_acceptance_deadline(): void
    {
        $combat = $this->combat(false);
        $hint = app(CombatQuery::class)->hints($this->opponent)[0];
        $this->assertSame('Herausforderung annehmen oder ablehnen', $hint['tasks'][0]['label']);
        $this->assertTrue($combat->expires_at->equalTo($hint['tasks'][0]['due_at']));
        $this->actingAs($this->opponent)->get('/dashboard')->assertOk()->assertSee('Herausforderung annehmen oder ablehnen');
    }

    public function test_unrepresented_rule_case_can_be_neutrally_ended_by_uninvolved_leader(): void
    {
        $combat = $this->activeCombat();
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Ein erzählerischer Sonderfall']);
        $decision = $combat->decisions()->where('type', 'ruling')->where('status', 'pending')->sole();
        $url = route('rpg.combats.decide', [$combat, $decision->id]);
        $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Ein erzählerischer Sonderfall')->assertSee('Mit Begründung neutral abbrechen');
        $this->actingAs($this->player)->postJson($url, ['abort' => true, 'reason' => 'Spielerentscheidung'])->assertForbidden();
        $this->actingAs($this->leader)->postJson($url, ['abort' => true, 'reason' => ''])->assertUnprocessable();
        $this->post($url, ['abort' => '1', 'choices' => ['context' => 'none'], 'reason' => 'Dieser Fall lässt sich nicht ausreichend abbilden.'])->assertRedirect();
        $combat->refresh();
        $this->assertSame('judicial_abort', $combat->result);
        $this->assertNull($combat->winner_side);
        $this->assertNull($combat->state['continuation']);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
        $this->assertSame(0, $combat->decisions()->whereIn('status', ['pending', 'paused'])->count());
        $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('Durch die AG-Leitung neutral abgebrochen');
        $this->post($url, ['abort' => '1', 'choices' => ['context' => 'none'], 'reason' => 'Dieser Fall lässt sich nicht ausreichend abbilden.'])->assertRedirect();
        $this->assertSame(3, RpgCombatMilestone::count());
    }
}

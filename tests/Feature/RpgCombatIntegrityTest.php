<?php

namespace Tests\Feature;

use App\Livewire\DashboardActivityFeed;
use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgCombat\CombatTraits;
use App\Support\RpgCharEditorRuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgCombatIntegrityTest extends TestCase
{
    use RefreshDatabase,RpgCombatFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatFixtures();
    }

    public function test_reverse_duplicate_and_self_challenges_are_rejected(): void
    {
        $combat = $this->combat(false);
        $this->actingAs($this->opponent)->postJson(route('rpg.combats.store'), $this->combatInput(['character_id' => $this->otherCharacter->id, 'opponent_id' => $this->character->id]))->assertUnprocessable();
        $owned = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->actingAs($this->player)->postJson(route('rpg.combats.store'), $this->combatInput(['opponent_id' => $owned->id]))->assertForbidden();
        $this->assertSame(1, RpgCombat::count());
    }

    public function test_active_character_cannot_accept_a_second_fight(): void
    {
        $first = $this->combat(false);
        $owned = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()])->refresh();
        $second = app(CombatService::class)->challenge($this->player, $this->combatInput(['character_id' => $owned->id]));
        app(CombatService::class)->command($this->opponent, $first->id, 'accept', (string) Str::uuid());
        $this->actingAs($this->opponent)->postJson(route('rpg.combats.command', $second), ['command' => 'accept', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('rpg_combat_character_locks', 2);
        $this->assertNull($second->fresh()->accepted_at);
    }

    public function test_edits_after_acceptance_do_not_change_fighting_snapshot(): void
    {
        $combat = $this->activeCombat();
        $before = $combat->state['actors'][1]['profile'];
        $payload = $this->character->payload;
        $payload['attributes']['st'] = 9;
        $this->character->update(['payload' => $payload]);
        $this->character->increment('revision');
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'wait']);
        $this->assertSame($before, $combat->state['actors'][1]['profile']);
        $this->assertSame(9, $this->character->fresh()->payload['attributes']['st']);
    }

    public function test_abort_requires_both_players_and_never_pauses_decisions(): void
    {
        $combat = $this->activeCombat();
        $service = app(CombatService::class);
        $combat = $service->command($this->player, $combat->id, 'abort', (string) Str::uuid());
        $this->assertTrue($combat->isOpen());
        $this->assertSame(1, $combat->decisions()->where('status', 'pending')->count());
        $combat = $service->command($this->opponent, $combat->id, 'abort', (string) Str::uuid());
        $this->assertSame('cancelled', $combat->result);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    }

    public function test_sealed_declaration_is_not_in_html_or_polling_json(): void
    {
        $combat = $this->combat();
        foreach ([1, 2] as $side) {
            $combat = $this->decision($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        foreach ([1, 2] as $side) {
            $combat = $this->decision($combat, 'initiative', $side);
        }
        $combat = $this->decision($combat, 'ruling', 0, ['choices' => ['initiative_tie' => 'simultaneous'], 'reason' => 'Gleiche Initiative bleibt erhalten.']);
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'GEHEIME-ANSAGE-8472']);
        $this->actingAs($this->opponent)->getJson(route('rpg.combats.show', $combat))->assertOk()->assertDontSee('GEHEIME-ANSAGE-8472');
        $this->actingAs($this->leader)->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('GEHEIME-ANSAGE-8472');
        $combat = $this->decision($combat, 'ruling', 0, ['choices' => ['context' => 'none'], 'reason' => 'Keine zusätzliche Wirkung.']);
        $this->actingAs($this->opponent)->get(route('rpg.combats.show', $combat))->assertOk()->assertDontSee('GEHEIME-ANSAGE-8472');
        $combat = $this->decision($combat, 'action', 2, ['kind' => 'wait']);
        $this->get(route('rpg.combats.show', $combat))->assertOk()->assertSee('GEHEIME-ANSAGE-8472');
    }

    public function test_current_leader_only_can_rule_and_participating_leader_cannot(): void
    {
        $combat = $this->activeCombat();
        $combat = $this->decision($combat, 'action', 1, ['kind' => 'creative', 'description' => 'Sand aufwirbeln.']);
        $decision = $combat->decisions()->where('type', 'ruling')->where('status', 'pending')->firstOrFail();
        $url = route('rpg.combats.decide', [$combat, $decision->id]);
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $this->actingAs($this->leader)->postJson($url, ['choices' => ['context' => 'none'], 'reason' => 'Auslegung'])->assertForbidden();
        $this->actingAs($this->player)->postJson($url, ['choices' => ['context' => 'plus_two'], 'reason' => 'Selbstentscheidung'])->assertForbidden();
        $this->travelTo($decision->due_at);
        app(CombatService::class)->decide(null, $combat->id, $decision->id);
        $this->assertTrue($decision->fresh()->automatic);
    }

    public function test_snapshot_omits_biography_and_accounts_for_all_traits(): void
    {
        $payload = $this->progressionPayload();
        $payload['character']['biography'] = 'PRIVATE-HISTORY';
        $payload['advantages'] = ['Kampfreflexe', 'Zäh', 'Gesteigertes Attribut'];
        $payload['equipment']['items'] = [['id' => 'revolver', 'quantity' => 2], ['id' => 'energiegewehr', 'quantity' => 1]];
        $this->character->update(['payload' => $payload]);
        $snapshot = app(CombatSnapshotFactory::class)->make($this->character);
        $this->assertStringNotContainsString('PRIVATE-HISTORY', json_encode($snapshot));
        $this->assertSame(6, $snapshot['weapons']['revolver:1']['loaded']);
        $this->assertSame(18, $snapshot['weapons']['revolver:2']['reserve']);
        $this->assertSame(100, $snapshot['weapons']['energiegewehr:1']['loaded']);
        $this->assertSame(0, $snapshot['weapons']['energiegewehr:1']['reserve']);
        $this->assertCount(4, CombatTraits::describe($snapshot));
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_saved_character_is_rejected_without_partial_invitation(array $changes): void
    {
        $payload = array_replace_recursive($this->progressionPayload(), $changes);
        $this->character->update(['payload' => $payload]);
        try {
            $this->combat(false);
            $this->fail('Expected invalid snapshot');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rpg_combats', 0);
        }
    }

    public static function invalidPayloads(): array
    {
        return array_map(fn ($p) => [$p], [
            ['attributes' => ['st' => 'six']], ['skills' => [['name' => 'Nahkampf', 'value' => -2]]],
            ['advantages' => [['wrong']]], ['advantage_counts' => ['Panzerung' => 100000]],
            ['advantages' => ['Unbekannt']], ['equipment' => ['items' => [['id' => 'missing', 'quantity' => 1]]]],
            ['equipment' => ['items' => [['id' => 'schwert', 'quantity' => 0]]]], ['equipment' => ['active_armor_id' => 'lederruestung']],
            ['advantages' => ['Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => '']]],
            ['rule_sources' => [['id' => 'invented']]],
            ['advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Pyrokinese']]],
            ['character' => ['race' => 'Marsianer']],
        ]);
    }

    public function test_public_dashboard_shows_only_milestones_and_escapes_names(): void
    {
        $this->character->update(['character_name' => '<script>attack()</script>']);
        $combat = $this->combat();
        $outsider = $this->progressionMember();
        Livewire::actingAs($outsider)->test(DashboardActivityFeed::class)
            ->set('filter', 'club')->assertSee('zum Übungskampf herausgefordert')->assertSee('beginnen ihren Übungskampf')
            ->assertDontSeeHtml('<script>attack()</script>')->assertDontSee('snapshot_hash')->assertDontSee('Kampfprotokoll');
        $this->actingAs($this->player)->get(route('dashboard'))->assertOk()->assertSee('Persönliche Übungskämpfe');
        $combat = app(CombatService::class)->command($this->player, $combat->id, 'surrender', (string) Str::uuid());
        Livewire::actingAs($outsider)->test(DashboardActivityFeed::class)->assertSee('Mira hat gewonnen.');
    }

    public function test_enabled_expansion_uses_saved_values_and_never_applies_race_bonuses_twice(): void
    {
        $payload = $this->progressionPayload();
        $payload['character']['race'] = 'Marsianer';
        $payload['rule_sources'] = RpgCharEditorRuleCatalog::snapshots(['expansion-1' => true]);
        $payload['attributes']['wa'] = 2;
        $this->character->update(['payload' => $payload]);
        $combat = $this->combat();
        $this->assertSame(2, $combat->state['actors'][1]['profile']['attributes']['wa']);
        $this->assertContains('expansion-1', array_column($combat->participants[0]->snapshot['sources'], 'id'));
    }

    public function test_participant_snapshot_is_immutable_and_original_links_remain_correct(): void
    {
        $combat = $this->combat();
        $participant = $combat->participants[0];
        $this->assertSame($combat->id, $participant->combat->id);
        $this->assertSame($combat->id, $combat->decisions()->first()->combat->id);
        $this->expectException(\LogicException::class);
        $participant->update(['snapshot' => ['forged' => true]]);
    }

    public function test_team_deletion_cancels_fights_and_decline_withdraw_are_idempotent(): void
    {
        $service = app(CombatService::class);
        $combat = $this->combat(false);
        $key = (string) Str::uuid();
        $service->command($this->opponent, $combat->id, 'decline', $key);
        $service->command($this->opponent, $combat->id, 'decline', $key);
        $this->assertSame('declined', $combat->fresh()->status);
        $combat = $this->combat(false);
        $service->command($this->player, $combat->id, 'withdraw', (string) Str::uuid());
        $this->assertSame('withdrawn', $combat->fresh()->status);
        $combat = $this->combat();
        $this->rpgTeam->delete();
        $this->assertSame('membership_changed', $combat->fresh()->result);
        $this->assertDatabaseCount('rpg_combat_character_locks', 0);
    }
}

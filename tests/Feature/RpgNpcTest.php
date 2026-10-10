<?php

namespace Tests\Feature;

use App\Models\RpgNpc;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Services\RpgNpcService;
use App\Support\RpgNpcCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgCombatFixtures;
use Tests\TestCase;

class RpgNpcTest extends TestCase
{
    use RefreshDatabase, RpgCombatFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatFixtures();
    }

    protected function npcInput(array $extra = []): array
    {
        return array_replace(['submission_key' => (string) Str::uuid(), 'template_key' => 'androne'], $extra);
    }

    public function test_every_published_template_builds_and_preserves_all_k_values(): void
    {
        $this->assertCount(23, RpgNpcCatalog::all());
        foreach (RpgNpcCatalog::all() as $key => $row) {
            $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => $key]));
            $profile = app(NpcCombatSnapshotFactory::class)->make($npc);
            $actor = app(CombatEngine::class)->start([$profile, $profile], 100, 100)['state']['actors'][1];
            $actor['shield'] = $profile['shield'] !== null;
            $this->assertSame($row['dodge'], array_sum(CombatStats::defense($actor, 'dodge', [], 1, false)), $key.' dodge');
            if ($row['parry'] !== null) {
                $this->assertSame($row['parry'], array_sum(CombatStats::defense($actor, 'parry', [], 1, false)), $key.' parry');
            }
            $defender = $actor;
            $defender['profile']['attributes'] = array_fill_keys(array_keys($row['attributes']), 0);
            $defender['profile']['advantages'] = $defender['profile']['disadvantages'] = [];
            $defender['profile']['armor'] = null;
            foreach ($profile['weapons'] as $id => $weapon) {
                $actor['held'] = $weapon['natural'] ? [] : [$id];
                foreach ($weapon['modes'] as $mode) {
                    if (! isset($mode['npc_attack'])) {
                        continue;
                    }
                    foreach ($mode['attributes'] as $attribute) {
                        $this->assertSame($mode['npc_attack'], array_sum(CombatStats::attack($actor, $weapon, $mode, [], 100, ['attribute' => $attribute])), $key.' '.$id.' '.$attribute);
                    }
                    $this->assertSame($mode['npc_damage'], array_sum(CombatStats::damage($actor, $defender, ['mode' => $mode, 'weapon' => $weapon, 'input' => [], 'distance' => 100], [])), $key.' damage '.$id);
                }
            }
            $this->assertSame(0, $npc->revision);
        }
    }

    public function test_creation_is_idempotent_shared_and_does_not_use_character_slots_or_money(): void
    {
        $input = $this->npcInput(['custom_name' => '  Wächter  ']);
        $service = app(RpgNpcService::class);
        $npc = $service->create($this->leader, $input);
        $this->assertSame($npc->id, $service->create($this->leader, $input)->id);
        $this->assertSame('Wächter', $npc->displayName());
        $service->create($this->leader, $this->npcInput());
        $this->assertDatabaseCount('rpg_npcs', 2);
        $this->assertDatabaseCount('rpg_characters', 2);
        $this->actingAs($this->leader)->get(route('rpg.characters.index'))->assertOk()->assertSee('Wächter');
        $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertOk()->assertDontSee('Wächter');
    }

    public function test_http_preview_creation_and_rename_and_delete(): void
    {
        $input = $this->npcInput(['custom_name' => '<script>Wächter</script>']);
        $this->actingAs($this->leader)->get(route('rpg.npcs.create'))->assertOk()->assertSee('Jacob Smythe');
        $this->post(route('rpg.npcs.preview'), $input)->assertOk()->assertSee('NSC verbindlich erstellen');
        $this->assertDatabaseCount('rpg_npcs', 0);
        $this->post(route('rpg.npcs.store'), $input)->assertRedirect();
        $npc = RpgNpc::firstOrFail();
        $this->get(route('rpg.npcs.show', $npc))->assertOk()->assertDontSee('<script>Wächter</script>', false);
        $this->patch(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Torwache'])->assertRedirect();
        $this->assertSame('Torwache', $npc->fresh()->displayName());
        $this->patchJson(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Alt'])->assertUnprocessable();
        $this->delete(route('rpg.npcs.destroy', $npc))->assertRedirect();
        $this->assertDatabaseCount('rpg_npcs', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'unknown' => [['template_key' => 'dragon']], 'profile injection' => [['profile' => []]],
            'special name' => [['template_key' => 'maddrax', 'custom_name' => 'Anderer']],
            'irrelevant rank' => [['rank' => 'Sol']], 'bad bandit weapon' => [['template_key' => 'bandit', 'weapon' => 'driller']],
            'rank budget' => [['template_key' => 'daamure', 'rank' => 'Lin']],
            'rank excess' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Nahkampf' => 4], 'reason' => 'Krieger']],
            'negative fp' => [['template_key' => 'daamure', 'skill_increases' => ['Nahkampf' => -1]]],
            'unknown skill' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Magie' => 3], 'reason' => 'Magier']],
            'intuition conflict' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Intuition' => 3], 'reason' => 'Intuitiv']],
            'science education' => [['template_key' => 'daamure', 'profession' => 'Wissenschaftler', 'rank' => 'Lin', 'skill_increases' => ['Wissenschaftler' => 3], 'reason' => 'Forscher']],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_never_create_npcs(array $extra): void
    {
        $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), $this->npcInput($extra))->assertUnprocessable();
        $this->assertDatabaseCount('rpg_npcs', 0);
    }

    public function test_all_ranks_apply_cumulative_attributes_and_exact_fp(): void
    {
        foreach (RpgNpcCatalog::ranks() as $rank => $values) {
            $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'daamure', 'rank' => $rank, 'profession' => 'Techniker',
                'skill_increases' => ['Nahkampf' => $values['fp']], 'reason' => 'Kampftraining']));
            foreach (array_diff_key($values, ['fp' => 1]) as $attribute => $value) {
                $this->assertSame($value, $npc->profile['attributes'][$attribute]);
            }
            $this->assertSame(3 + $values['fp'], $npc->profile['skills']['Nahkampf']);
            $this->assertSame(2, $npc->profile['advantage_counts']['Regeneration']);
        }
    }

    public function test_specials_are_unique_and_only_current_active_leader_can_manage(): void
    {
        $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'maddrax']));
        $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), $this->npcInput(['template_key' => 'maddrax']))->assertUnprocessable();
        $this->patchJson(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Fake'])->assertForbidden();
        $this->actingAs($this->player)->get(route('rpg.npcs.create'))->assertForbidden();
        $this->get(route('rpg.npcs.show', $npc))->assertForbidden();
        $this->postJson(route('rpg.npcs.store'), $this->npcInput())->assertForbidden();
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $this->get(route('rpg.npcs.show', $npc))->assertOk()->assertSee('Matthew Drax');
        $this->actingAs($this->leader)->deleteJson(route('rpg.npcs.destroy', $npc))->assertForbidden();
    }

    public function test_immutable_profile_cannot_be_modified_through_model(): void
    {
        $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput());
        $this->expectException(\LogicException::class);
        $npc->update(['profile' => ['name' => 'Gefälscht']]);
    }

    public function test_reused_submission_key_with_changed_data_is_a_conflict(): void
    {
        $input = $this->npcInput();
        app(RpgNpcService::class)->create($this->leader, $input);
        $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), array_replace($input, ['custom_name' => 'Anders']))->assertConflict();
        $this->assertDatabaseCount('rpg_npcs', 1);
    }

    public static function banditWeapons(): array
    {
        return ['sword' => ['schwert', 2, 1], 'dagger' => ['messer-dolch', 2, 0], 'sling' => ['zwille', 2, 0], 'bow' => ['bogen', 1, 1]];
    }

    #[DataProvider('banditWeapons')]
    public function test_bandit_has_only_selected_equipment_and_its_published_k_values(string $id, int $attack, int $damage): void
    {
        $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'bandit', 'weapon' => $id]));
        $this->assertCount(2, $npc->profile['weapons']); // Selected weapon and the shared unarmed attack.
        $this->assertSame($attack, $npc->profile['weapons'][$id.':1']['modes'][0]['npc_attack']);
        $this->assertSame($damage, $npc->profile['weapons'][$id.':1']['modes'][0]['npc_damage']);
    }
}

<?php

namespace Tests\Feature;

use App\Services\RpgCharacterAdvancementEvaluator;
use App\Services\RpgCharacterProgressionAdapter;
use App\Services\RpgExperienceAwardCalculator;
use App\Support\RpgCharEditorEquipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgProgressionRulesTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    public static function durations(): array
    {
        return [[359, 1], [360, 2], [719, 2], [720, 3], [7200, 21]];
    }

    #[DataProvider('durations')]
    public function test_awards_round_duration_down_without_an_eleven_point_cap(int $minutes, int $points): void
    {
        $result = app(RpgExperienceAwardCalculator::class)->calculate($minutes, 0, ['survived' => true, 'roleplay' => 0, 'humor' => false, 'rescue' => false, 'unwounded' => false]);
        $this->assertSame($points, $result['points']);
    }

    public function test_award_criteria_and_override(): void
    {
        $input = ['survived' => true, 'roleplay' => 2, 'humor' => true, 'rescue' => true, 'unwounded' => true];
        $result = app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input);
        $this->assertSame(11, $result['points']);
        $this->assertSame(0, app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input + ['points' => 0, 'reason' => 'Nachteile umgangen'])['points']);
        $this->expectException(ValidationException::class);
        app(RpgExperienceAwardCalculator::class)->calculate(360, 4, $input + ['points' => 0]);
    }

    public function test_multiple_skill_steps_have_cumulative_cost_and_ignore_creation_limit(): void
    {
        $result = $this->evaluate([$this->operation(steps: 4)]);
        $this->assertSame(48, $result['cost']);
        $this->assertSame(6, $result['changes'][0]['after']);
    }

    public function test_attributes_and_targeted_advantages_use_separate_limits(): void
    {
        $result = $this->evaluate([$this->operation('attribute', 'st'), $this->operation('advantage', 'Gesteigertes Attribut', extra: ['target' => 'st'])]);
        $this->assertSame(50, $result['cost']);
        $this->assertSame(2, $result['payload']['attributes']['st']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('attribute', 'st', 2)]);
    }

    public function test_expansion_race_modifier_is_respected(): void
    {
        $payload = $this->progressionPayload();
        $payload['character']['race'] = 'Morlock';
        $payload['attributes']['in'] = -1;
        $result = $this->evaluate([$this->operation('attribute', 'in')], $payload);
        $this->assertSame(0, $result['payload']['attributes']['in']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('attribute', 'in', 2)], $payload);
    }

    public function test_repeatable_advantages_and_shapechanger_price(): void
    {
        $result = $this->evaluate([$this->operation('advantage', 'Panzerung'), $this->operation('advantage', 'Panzerung'), $this->operation('advantage', 'Gestaltwandler')]);
        $this->assertSame(100, $result['cost']);
        $this->assertSame(2, $result['payload']['advantage_counts']['Panzerung']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('advantage', 'Zäh'), $this->operation('advantage', 'Zäh')]);
    }

    public function test_disadvantage_removal_does_not_refund_creation_budget(): void
    {
        $payload = $this->progressionPayload();
        $payload['creation'] = ['attribute_budget' => ['used' => 2]];
        $result = $this->evaluate([$this->operation('disadvantage', 'Lichtscheu')], $payload);
        $this->assertSame(20, $result['cost']);
        $this->assertSame([], $result['payload']['disadvantages']);
        $this->assertSame($payload['creation'], $result['payload']['creation']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('disadvantage', 'Lichtscheu'), $this->operation('disadvantage', 'Lichtscheu')]);
    }

    public function test_psychic_skills_require_power_and_cost_twice_as_much(): void
    {
        $result = $this->evaluate([$this->operation('advantage', 'Psychische Kraft', extra: ['target' => 'Telepathie']), $this->operation('skill', 'Telepathie', 4)]);
        $this->assertSame(80, $result['cost']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Telepathie')]);
    }

    public function test_related_operations_can_establish_prerequisites(): void
    {
        $result = $this->evaluate([$this->operation('advantage', 'Kind zweier Welten'), $this->operation('skill', 'Intuition')]);
        $this->assertSame(26, $result['cost']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Intuition')]);
    }

    public function test_new_languages_must_be_named_and_existing_languages_preserved(): void
    {
        $result = $this->evaluate([$this->operation('skill', 'Sprachen', extra: ['languages' => ['Französisch']])]);
        $this->assertSame(['Französisch'], $result['payload']['languages']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Sprachen')]);
    }

    public static function invalidOperations(): array
    {
        return [
            [['type' => 'attribute', 'name' => 'unknown']],
            [['type' => 'advantage', 'name' => 'Unknown']],
            [['type' => 'skill', 'name' => 'Wissenschaftler', 'steps' => 2]],
            [['type' => 'skill', 'name' => 'Unknown']],
            [['type' => 'skill', 'name' => 'Nahkampf', 'steps' => -1]],
            [['type' => 'skill', 'name' => 'Nahkampf', 'reason' => ' ']],
            [['type' => 'advantage', 'name' => 'Gesteigerter Sinn', 'target' => 'unknown']],
            [['type' => 'advantage', 'name' => 'High-Tech-Ausrüstung']],
            [['type' => 'advantage', 'name' => 'Panzerung', 'steps' => 2]],
            [['type' => 'skill', 'name' => 'Nahkampf', 'cost' => 0]],
            [['type' => 'skill', 'name' => 'Nahkampf', 'target' => 'st']],
            [['type' => 'skill', 'name' => 'Nahkampf', 'items' => ['a', 'b', 'c', 'd']]],
            [['type' => 'skill', 'name' => 'Nahkampf', 'languages' => ['Englisch']]],
            [['type' => 'advantage', 'name' => 'Panzerung', 'items' => ['a', 'b', 'c', 'd']]],
            [['type' => 'advantage', 'name' => 'High-Tech-Ausrüstung', 'items' => ['invalid', 'invalid', 'invalid', 'invalid']]],
            [['type' => 'skill', 'name' => 'Sprachen', 'languages' => ['Englisch', 'Englisch']]],
        ];
    }

    public function test_high_tech_purchase_merges_inventory_and_preserves_existing_notes(): void
    {
        $items = array_values(array_filter(RpgCharEditorEquipment::items(), RpgCharEditorEquipment::requiresHighTechAdvantage(...)));
        $id = $items[0]['id'];
        $payload = $this->progressionPayload();
        $payload['equipment'] = ['notes' => 'Persönlicher Gegenstand', 'items' => [['id' => $id, 'name' => $items[0]['name'], 'quantity' => 1]]];
        $result = $this->evaluate([$this->operation('advantage', 'High-Tech-Ausrüstung', extra: ['items' => array_fill(0, 4, $id)])], $payload);
        $this->assertSame(20, $result['cost']);
        $this->assertCount(1, $result['payload']['equipment']['items']);
        $this->assertSame(5, $result['payload']['equipment']['items'][0]['quantity']);
        $this->assertSame('Persönlicher Gegenstand', $result['payload']['equipment']['notes']);
        $this->assertArrayHasKey('ammunition', $result['payload']['equipment']);
    }

    public function test_unique_targets_and_repeatable_regeneration(): void
    {
        $first = $this->operation('advantage', 'Gesteigerter Sinn', extra: ['target' => 'Sehen']);
        $second = $this->operation('advantage', 'Gesteigerter Sinn', extra: ['target' => 'Hören']);
        $result = $this->evaluate([$first, $second, $this->operation('advantage', 'Regeneration'), $this->operation('advantage', 'Regeneration')]);
        $this->assertSame(2, $result['payload']['advantage_counts']['Regeneration']);
        $this->assertSame(2, $result['payload']['advantage_counts']['Gesteigerter Sinn']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$first, $first]);
    }

    public function test_primitive_must_be_removed_before_learning_education(): void
    {
        $payload = $this->progressionPayload();
        $payload['skills'] = [];
        $payload['disadvantages'] = ['Primitiv'];
        $result = $this->evaluate([$this->operation('disadvantage', 'Primitiv'), $this->operation('skill', 'Bildung')], $payload);
        $this->assertSame(26, $result['cost']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Bildung')], $payload);
    }

    public function test_specialized_science_cannot_exceed_education(): void
    {
        $result = $this->evaluate([$this->operation('skill', 'Wissenschaftler: Medizin')]);
        $this->assertSame(6, $result['cost']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Wissenschaftler: Medizin', 2)]);
    }

    public function test_language_capacity_can_expand_but_known_languages_cannot_be_replaced(): void
    {
        $payload = $this->progressionPayload();
        $payload['skills'][] = ['name' => 'Sprachen', 'value' => 1];
        $payload['languages'] = ['Englisch'];
        $result = $this->evaluate([$this->operation('advantage', 'Sprachbegabt', extra: ['languages' => ['Englisch', 'Französisch', 'Deutsch']])], $payload);
        $this->assertCount(3, $result['payload']['languages']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('skill', 'Sprachen', extra: ['languages' => ['Deutsch', 'Französisch']])], $payload);
    }

    public function test_legacy_aliases_counts_and_original_creation_values_are_preserved(): void
    {
        $payload = $this->progressionPayload();
        $payload['advantages'] = ['Zaeh', 'Panzerung'];
        $payload['advantage_counts'] = ['Panzerung' => 2];
        $payload['creation'] = ['attribute_race_modifiers' => ['st' => 1], 'origin' => 'Original'];
        $payload['attributes']['st'] = -2;
        $result = $this->evaluate([$this->operation('attribute', 'st', 3)], $payload);
        $this->assertContains('Zäh', $result['payload']['advantages']);
        $this->assertCount(3, $result['payload']['advantage_effects']);
        $this->assertSame(1, $result['payload']['attributes']['st']);
        $this->assertSame($payload['creation'], $result['payload']['creation']);
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('advantage', 'Zäh')], $payload);
    }

    public function test_unambiguous_barbar_origin_can_be_recovered_but_ambiguous_origin_is_blocked(): void
    {
        $payload = $this->progressionPayload();
        $payload['character']['race'] = 'Barbar';
        $payload['attributes']['st'] = 2;
        $adapter = new RpgCharacterProgressionAdapter;
        $this->assertSame(['st' => 1], $adapter->raceModifiers($adapter->normalize($payload)));
        $payload['attributes']['st'] = 1;
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('attribute', 'ge')], $payload);
    }

    public function test_unknown_legacy_attribute_bonus_requires_clarification(): void
    {
        $payload = $this->progressionPayload();
        $payload['advantages'] = ['Gesteigertes Attribut'];
        $this->expectException(ValidationException::class);
        $this->evaluate([$this->operation('attribute', 'st')], $payload);
    }

    public static function invalidAwards(): array
    {
        return [[['points' => -1]], [['points' => 1.5]], [['roleplay' => 3]], [['humor' => 2]], [['points' => 10, 'reason' => '   ']]];
    }

    #[DataProvider('invalidAwards')]
    public function test_invalid_award_values_are_rejected(array $input): void
    {
        $this->expectException(ValidationException::class);
        app(RpgExperienceAwardCalculator::class)->calculate(360, 0, $input + ['survived' => true, 'roleplay' => 0, 'humor' => false, 'rescue' => false, 'unwounded' => false]);
    }

    public function test_every_roleplay_and_cycle_combination(): void
    {
        foreach ([0, 1, 2] as $roleplay) {
            foreach ([0, 1, 2, 4] as $cycle) {
                $result = app(RpgExperienceAwardCalculator::class)->calculate(0, $cycle, ['survived' => false, 'roleplay' => $roleplay, 'humor' => true, 'rescue' => true, 'unwounded' => true]);
                $this->assertSame($roleplay + $cycle + 3, $result['points']);
            }
        }
    }

    #[DataProvider('invalidOperations')]
    public function test_invalid_improvements_are_rejected(array $operation): void
    {
        $this->expectException(ValidationException::class);
        $this->evaluate([$operation + ['reason' => 'Begründung']]);
    }

    private function evaluate(array $operations, ?array $payload = null): array
    {
        return app(RpgCharacterAdvancementEvaluator::class)->evaluate($payload ?? $this->progressionPayload(), $operations);
    }
}

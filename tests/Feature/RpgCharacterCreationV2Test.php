<?php

namespace Tests\Feature;

use App\Services\RpgCharacterSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RpgCharacterCreationV2Test extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'figurenstaerke' => 3,
            'player_name' => 'Regeltest',
            'character_name' => 'Niveau Drei',
            'gender' => 'maennlich',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'description' => '',
            'barbar_attribute_bonus' => 'st',
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Überleben', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf: Viehzüchter', 'value' => 2],
                ['name' => 'Kunde: Wetter', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => [],
            'advantage_effects' => [],
            'languages' => [],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'bogen', 'quantity' => 1],
            ],
        ], $overrides);

        foreach (['attribute_adjustments', 'attributes', 'skills', 'advantages', 'disadvantages', 'advantage_effects', 'languages', 'equipment_items'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $payload[$key] = $overrides[$key];
            }
        }

        return $payload;
    }

    private function validate(array $payload): array
    {
        return app(RpgCharacterSheetService::class)->validatedPdfPayload(Request::create('/', 'POST', $payload));
    }

    public function test_v2_payload_persists_rule_origin_and_one_free_advantage(): void
    {
        $data = $this->validate($this->payload([
            'advantages' => ['Zäh', 'Schnell'],
            'advantage_effects' => [['name' => 'Schnell', 'target' => '', 'justification' => 'Nanotechnologie']],
        ]));

        $this->assertSame(['edition' => 2007, 'creation_level' => 3, 'payload_version' => 2], $data['rules']);
        $this->assertSame(1, $data['creation']['advantage_budget']['used']);
        $this->assertSame(0, $data['creation']['advantage_budget']['required_compensations']);
        $this->assertSame(1, $data['creation']['attribute_adjustments']['st']);
        $this->assertContains('Schnell', $data['advantages']);
    }

    public function test_v2_rejects_missing_compensation_and_accepts_gestaltwandler_with_two(): void
    {
        try {
            $this->validate($this->payload([
                'advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'],
                'advantage_effects' => [
                    ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                    ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
                ],
            ]));
            $this->fail('Eine fehlende Kompensation wurde nicht abgelehnt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disadvantages', $exception->errors());
        }

        $data = $this->validate($this->payload([
            'advantages' => ['Zäh', 'Gestaltwandler'],
            'disadvantages' => ['Auffällig', 'Blutdurst'],
            'advantage_effects' => [['name' => 'Gestaltwandler', 'target' => '', 'justification' => '']],
        ]));

        $this->assertSame(3, $data['creation']['advantage_budget']['used']);
        $this->assertSame(2, $data['creation']['advantage_budget']['available_compensations']);
    }

    public function test_v2_rejects_client_attribute_value_that_disagrees_with_evaluator(): void
    {
        $this->expectException(ValidationException::class);

        $this->validate($this->payload([
            'attributes' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
        ]));
    }

    public function test_v2_ap_trade_and_enhanced_attribute_are_calculated_server_side(): void
    {
        $data = $this->validate($this->payload([
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1],
            'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1, 'wi' => 1, 'wa' => 0, 'in' => 0, 'au' => -1],
            'extra_ap_attribute' => 'au',
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation']],
        ]));

        $this->assertSame(3, $data['creation']['attribute_budget']['used']);
        $this->assertSame(1, $data['creation']['attribute_budget']['extra']);
        $this->assertSame('1', $data['attributes']['wi']);
    }

    public function test_v2_validates_language_capacity_with_and_without_sprachbegabt(): void
    {
        $skills = [...$this->payload()['skills'], ['name' => 'Sprachen', 'value' => 2]];

        try {
            $this->validate($this->payload(['skills' => $skills, 'languages' => ['Deutsch', 'Englisch', 'Französisch']]));
            $this->fail('Zu viele Sprachen ohne Sprachbegabt wurden nicht abgelehnt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('languages', $exception->errors());
        }

        $data = $this->validate($this->payload([
            'skills' => $skills,
            'advantages' => ['Zäh', 'Sprachbegabt'],
            'advantage_effects' => [['name' => 'Sprachbegabt', 'target' => '', 'justification' => '']],
            'languages' => ['Deutsch', 'Englisch', 'Französisch', 'Schwedisch', 'Dänisch', 'Finnisch'],
        ]));

        $this->assertCount(6, $data['languages']);
    }

    public function test_v2_requires_exactly_four_high_tech_items_with_advantage(): void
    {
        $threeHighTech = [
            ['id' => 'fernglas', 'quantity' => 1],
            ['id' => 'funkgeraet', 'quantity' => 1],
            ['id' => 'gasmaske', 'quantity' => 1],
            ['id' => 'seil', 'quantity' => 1],
            ['id' => 'rucksack', 'quantity' => 1],
            ['id' => 'wochenration', 'quantity' => 1],
        ];

        try {
            $this->validate($this->payload([
                'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
                'advantage_effects' => [['name' => 'High-Tech-Ausrüstung', 'target' => '', 'justification' => '']],
                'equipment_items' => $threeHighTech,
            ]));
            $this->fail('Drei High-Tech-Gegenstände wurden akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('equipment_items', $exception->errors());
        }

        $fourHighTech = $threeHighTech;
        $fourHighTech[3] = ['id' => 'atemgeraet', 'quantity' => 1];
        $data = $this->validate($this->payload([
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung'],
            'advantage_effects' => [['name' => 'High-Tech-Ausrüstung', 'target' => '', 'justification' => '']],
            'equipment_items' => $fourHighTech,
        ]));

        $this->assertCount(6, $data['equipment']['items']);
    }

    public function test_v2_rejects_legacy_natural_weapon_skill(): void
    {
        $this->expectException(ValidationException::class);

        $this->validate($this->payload([
            'skills' => [...$this->payload()['skills'], ['name' => 'Natürliche Waffen', 'value' => 1]],
            'advantages' => ['Zäh', 'Natürliche Waffen'],
            'advantage_effects' => [[
                'name' => 'Natürliche Waffen',
                'target' => '',
                'justification' => 'Klauen',
            ]],
        ]));
    }

    public function test_v2_volk_der_dreizehn_inseln_requires_paid_telepathy(): void
    {
        $skills = [
            ['name' => 'Nahkampf', 'value' => 1],
            ['name' => 'Überleben', 'value' => 1],
            ['name' => 'Intuition', 'value' => 1],
            ['name' => 'Athletik', 'value' => 1],
            ['name' => 'Beruf: Bauer', 'value' => 1],
        ];

        try {
            $this->validate($this->payload([
                'gender' => 'weiblich',
                'culture' => 'Volk der 13 Inseln',
                'skills' => $skills,
                'advantages' => ['Zäh', 'Psychische Kraft'],
                'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Empathie', 'justification' => '']],
            ]));
            $this->fail('Eine andere psychische Kraft als Telepathie wurde akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('advantage_effects', $exception->errors());
        }

        $data = $this->validate($this->payload([
            'gender' => 'weiblich',
            'culture' => 'Volk der 13 Inseln',
            'skills' => $skills,
            'advantages' => ['Zäh', 'Psychische Kraft'],
            'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => '']],
        ]));

        $this->assertSame(1, $data['creation']['advantage_budget']['used']);
        $this->assertSame('Telepathie', collect($data['advantage_effects'])->firstWhere('name', 'Psychische Kraft')['target']);
    }

    public function test_v2_requires_creation_level_three_and_all_seven_adjustments(): void
    {
        foreach ([null, 1, 4] as $level) {
            $payload = $this->payload();
            if ($level === null) {
                unset($payload['figurenstaerke']);
            } else {
                $payload['figurenstaerke'] = $level;
            }

            try {
                $this->validate($payload);
                $this->fail('Eine ungültige Figurenstärke wurde akzeptiert.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('figurenstaerke', $exception->errors());
            }
        }

        $payload = $this->payload();
        unset($payload['attribute_adjustments']['au']);

        try {
            $this->validate($payload);
            $this->fail('Eine unvollständige Attributherkunft wurde akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attribute_adjustments.au', $exception->errors());
        }
    }

    public function test_v2_derives_repeat_counts_and_attribute_bonuses_from_effect_instances(): void
    {
        $data = $this->validate($this->payload([
            'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 0, 'wi' => 1, 'wa' => 1, 'in' => 0, 'au' => 0],
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'disadvantages' => ['Auffällig'],
            'advantage_counts' => ['Gesteigertes Attribut' => 20],
            'advantage_effects' => [
                ['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'],
                ['name' => 'Gesteigertes Attribut', 'target' => 'wa', 'justification' => 'Implantat'],
            ],
        ]));

        $this->assertSame(2, $data['advantage_counts']['Gesteigertes Attribut']);
        $this->assertSame(1, $data['creation']['attribute_advantage_bonuses']['wi']);
        $this->assertSame(1, $data['creation']['attribute_advantage_bonuses']['wa']);
        $this->assertSame('1', $data['attributes']['wi']);
        $this->assertSame('1', $data['attributes']['wa']);
    }

    public function test_v2_negates_racial_disadvantage_and_never_counts_remaining_racial_one_as_compensation(): void
    {
        $data = $this->validate($this->payload([
            'race' => 'Guul',
            'culture' => 'Stadtbewohner',
            'barbar_attribute_bonus' => '',
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'attributes' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1],
            'skills' => [
                ['name' => 'Heimlichkeit', 'value' => 2],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf', 'value' => 1],
                ['name' => 'Kunde', 'value' => 1],
                ['name' => 'Unterhalten', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Gejagt'],
            'negated_racial_disadvantages' => ['Primitiv'],
        ]));

        $this->assertNotContains('Primitiv', $data['disadvantages']);
        $this->assertContains('Gejagt', $data['disadvantages']);
        $this->assertSame(1, $data['creation']['advantage_budget']['used']);
        $this->assertSame(0, $data['creation']['advantage_budget']['available_compensations']);
    }

    public function test_v2_rejects_legacy_language_specializations_and_requires_madness_trigger(): void
    {
        try {
            $this->validate($this->payload([
                'skills' => [...$this->payload()['skills'], ['name' => 'Sprachen: Deutsch', 'value' => 1]],
            ]));
            $this->fail('Eine alte Sprachspezialisierung wurde akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skills', $exception->errors());
        }

        try {
            $this->validate($this->payload([
                'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
            ]));
            $this->fail('Anfälligkeit gegen Wahnsinn wurde ohne Auslöser akzeptiert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disadvantage_details', $exception->errors());
        }

        $data = $this->validate($this->payload([
            'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
            'disadvantage_details' => ['Anfälligkeit gegen Wahnsinn' => 'Fleischverzehr'],
        ]));

        $this->assertSame('Fleischverzehr', $data['disadvantage_details']['Anfälligkeit gegen Wahnsinn']);
    }
}

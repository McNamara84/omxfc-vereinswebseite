<?php

namespace Tests\Unit;

use App\Services\RpgCharacterSheetService;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;

class RpgCharEditorTrainingTest extends TestCase
{
    public function test_training_costs_and_special_rules_match_the_rulebook_tables(): void
    {
        $definitions = RpgCharEditorTraining::definitions();

        $this->assertSame([
            'Arbeiter' => 5,
            'Arzt (Heilkundiger)' => 5,
            'Dieb' => 6,
            'Forscher' => 6,
            'Händler' => 5,
            'Krieger' => 5,
            "Rev'rend" => 15,
            'Schamane (Göttersprecher)' => 5,
            'Seher' => 5,
            'Truveer' => 6,
        ], array_map(static fn (array $training): int => $training['cost'], $definitions));
        $this->assertSame(['Psychische Kraft'], $definitions['Seher']['requiredAdvantages']);
        $this->assertSame(['Unterhalten' => 'Predigen'], $definitions["Rev'rend"]['suggestedSpecializations']);
        $this->assertSame(
            ['Nahkampf', 'Fernkampf', 'Feuerwaffen', 'Reiten', 'Fahren', 'Pilot'],
            $definitions['Krieger']['skills'],
        );
    }

    public function test_every_training_skill_is_a_known_or_specializable_base_skill(): void
    {
        $skillRules = RpgCharacterSheetService::skillRuleConfig();
        $knownSkills = array_column($skillRules['skills'], 'name');

        foreach (RpgCharEditorTraining::definitions() as $training) {
            $this->assertNotEmpty($training['description']);
            $this->assertGreaterThan(0, $training['cost']);
            $this->assertNotEmpty($training['skills']);

            foreach ($training['skills'] as $skill) {
                $this->assertContains($skill, $knownSkills, "Unknown skill {$skill} in {$training['name']}.");
            }
        }
    }

    public function test_rule_config_is_a_json_friendly_list_with_backend_limits(): void
    {
        $config = RpgCharEditorTraining::ruleConfig();

        $this->assertSame(RpgCharEditorTraining::MAX_TRAININGS, $config['maxTrainings']);
        $this->assertSame(RpgCharEditorTraining::MAX_ALLOCATIONS, $config['maxAllocations']);
        $this->assertSame(array_values(RpgCharEditorTraining::definitions()), $config['trainings']);
    }
}

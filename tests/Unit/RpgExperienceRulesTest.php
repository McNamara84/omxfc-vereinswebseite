<?php

namespace Tests\Unit;

use App\Services\RpgCharacterPsychicCalculator;
use App\Support\RpgExperienceRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RpgExperienceRulesTest extends TestCase
{
    public static function prices(): array
    {
        return [[1, 6], [2, 6], [3, 6], [4, 12], [5, 12], [6, 18], [20, 18]];
    }

    #[DataProvider('prices')]
    public function test_skill_cost_depends_on_each_target_value(int $target, int $cost): void
    {
        $this->assertSame($cost, RpgExperienceRules::skillCost($target));
        $this->assertSame($cost * 2, RpgExperienceRules::skillCost($target, true));
    }

    public function test_shapechanger_costs_three_advantages(): void
    {
        $this->assertSame(60, RpgExperienceRules::advantageCost('Gestaltwandler'));
        $this->assertSame(20, RpgExperienceRules::advantageCost('Panzerung'));
    }

    public function test_psychic_capacity_latent_powers_and_reservoir(): void
    {
        $payload = [
            'attributes' => ['wi' => 1],
            'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie'], ['name' => 'Psychische Kraft', 'target' => 'Empathie']],
            'skills' => [['name' => 'Telepathie', 'value' => 3]],
            'advantages' => [],
        ];
        $calculator = new RpgCharacterPsychicCalculator;
        $result = $calculator->calculate($payload);
        $this->assertSame(9, $result['pep']);
        $this->assertTrue($result['powers'][0]['usable']);
        $this->assertFalse($result['powers'][1]['usable']);
        $payload['advantages'][] = 'Psychisches Reservoir';
        $this->assertSame(18, $calculator->calculate($payload)['pep']);
        $payload['attributes']['wi'] = -1;
        $this->assertFalse($calculator->calculate($payload)['powers'][0]['usable']);
    }
}

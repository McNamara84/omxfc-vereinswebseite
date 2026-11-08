<?php

namespace Tests\Unit;

use App\Services\RpgItemCreationEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RpgItemCreationRulesTest extends TestCase
{
    private function input(array $changes = []): array
    {
        return array_replace(['name' => 'Ruinenschwert', 'description' => 'Eine alte Klinge.', 'category' => 'melee_weapons',
            'size' => 'medium', 'rarity' => 'common', 'bucks' => 100, 'reference' => 'Schwert auf S. 41', 'hands' => 1], $changes);
    }

    public static function sizes(): array
    {
        return [['small', false, 0, 1], ['medium', false, 1, 4], ['large', false, 2, 12],
            ['small', true, 1, 4], ['medium', true, 2, 12], ['large', true, 3, 32]];
    }

    #[DataProvider('sizes')]
    public function test_melee_size_and_masterwork(string $size, bool $masterwork, int $damage, int $tw): void
    {
        $result = (new RpgItemCreationEvaluator)->evaluate($this->input(compact('size', 'masterwork')));
        $this->assertSame($damage, $result['definition']['combat']['modes'][0]['damage']);
        $this->assertSame((string) $tw, $result['definition']['tw']);
    }

    public static function ranged(): array
    {
        return [['Wurfgeschoss', 0, 0, 0], ['Projektilwaffe', 1, 0, 0], ['Schießpulverwaffe', 2, 2, 6], ['Energiewaffe', 3, 3, 50]];
    }

    #[DataProvider('ranged')]
    public function test_ranged_rules_and_magazine_rounding(string $type, int $damage, int $education, int $magazine): void
    {
        $input = $this->input(['category' => 'ranged_weapons', 'weapon_type' => $type, 'precision' => -1, 'range_increment' => 10,
            'max_range' => 50, 'fire_rate' => 'E', 'magazine' => $magazine, 'ammunition_id' => 'ammo:pfeile']);
        $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
        $this->assertSame($damage, $result['combat']['modes'][0]['damage']);
        $this->assertSame($education, $result['minimumEducation']);
        $this->assertSame((string) ((4 + intdiv($magazine, 5)) * ($damage + 1)), $result['tw']);
        $this->assertSame($damage >= 2 ? 'Feuerwaffen' : 'Fernkampf', $result['combat']['modes'][0]['skill']);
    }

    public function test_armor_signed_penalty_then_technology_then_utility(): void
    {
        $input = $this->input(['category' => 'armor', 'effectiveness' => 2, 'movement_modifier' => -1, 'high_tech' => true, 'tech_factor' => 5, 'utility' => 2]);
        unset($input['hands']);
        $result = (new RpgItemCreationEvaluator)->evaluate($input);
        $this->assertSame(3, $result['definition']['combat']['protection']);
        $this->assertSame('220', $result['definition']['tw']); // (2 * 4 * 3 - 2) * 5 * 2
        $this->assertSame([1, 2, 2, 2, 2, 24, 22, 110, 220], array_column($result['calculation'], 'value'));
    }

    public function test_shield_never_gets_an_invented_material_bonus(): void
    {
        $input = $this->input(['category' => 'shields', 'material' => 6]);
        unset($input['hands']);
        $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
        $this->assertSame(['kind' => 'shield', 'defenseBonus' => 1], $result['combat']);
        $this->assertSame('8', $result['tw']);
    }

    public function test_combined_weapon_has_separate_damage_and_one_weapon_factor(): void
    {
        $result = (new RpgItemCreationEvaluator)->evaluate($this->input(['throwable' => true, 'weapon_type' => 'Wurfgeschoss',
            'precision' => 0, 'fire_rate' => 'E', 'range_increment' => 3, 'max_range' => 15]))['definition'];
        $this->assertSame([1, 0], array_column($result['combat']['modes'], 'damage'));
        $this->assertSame('10', $result['tw']);
        $this->assertArrayNotHasKey('ammunitionProfile', $result);
    }

    public function test_transport_values_and_ordinary_items_have_no_invented_combat_profile(): void
    {
        $input = $this->input(['category' => 'transport', 'persons_min' => 2, 'persons_max' => 4, 'protection' => 3,
            'land_speed' => 50, 'water_speed' => 20, 'air_speed' => 0, 'hover' => true, 'minimum_education' => 2]);
        unset($input['hands']);
        $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
        $this->assertSame(4, $result['transport']['persons_max']);
        $this->assertTrue($result['transport']['hover']);
        $this->assertStringContainsString('50/20/0 km/h', $result['summary']);
        $this->assertArrayNotHasKey('combat', $result);
        $input = $this->input(['category' => 'high_tech', 'high_tech' => true, 'tech_factor' => 1000, 'material' => 6,
            'beautiful' => true, 'rarity' => 'very_rare', 'utility' => 10]);
        unset($input['hands']);
        $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
        $this->assertSame('160000', $result['tw']);
        $this->assertArrayNotHasKey('combat', $result);
    }

    public static function invalidRanged(): array
    {
        return [[['precision' => -3]], [['precision' => 3]], [['fire_rate' => 'B']], [['range_increment' => 0]],
            [['max_range' => 9]], [['magazine' => -1]], [['weapon_type' => 'Laser']], [['weapon_type' => 'Schießpulverwaffe', 'magazine' => 0]],
            [['weapon_type' => 'Energiewaffe', 'magazine' => 6, 'minimum_education' => 0]], [['ammunition_id' => []]],
            [['weapon_type' => 'Wurfgeschoss', 'magazine' => 6]], [['ammunition_id' => '', 'ammunition_name' => '', 'ammunition_unit' => 'Schuss']]];
    }

    #[DataProvider('invalidRanged')]
    public function test_invalid_ranged_values_fail(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RpgItemCreationEvaluator)->evaluate($this->input($changes + ['category' => 'ranged_weapons', 'weapon_type' => 'Projektilwaffe',
            'precision' => 0, 'fire_rate' => 'E', 'range_increment' => 10, 'max_range' => 50, 'magazine' => 0, 'ammunition_id' => 'ammo:pfeile']));
    }

    public static function invalid(): array
    {
        return [
            [['damage' => 99]], [['protection' => 99]], [['category' => 'unknown']], [['material' => 1]],
            [['high_tech' => true, 'tech_factor' => 4]], [['tech_factor' => 2]], [['utility' => 11]], [['bucks' => -1]],
            [['name' => ' ']], [['description' => []]], [['reference' => '']], [['size' => 'huge']], [['hands' => 3]],
            [['attributes' => []]], [['attributes' => ['wi']]], [['attributes' => ['st', 'st']]], [['beautiful' => 'yes']],
            [['category' => 'armor', 'hands' => 1]], [['bucks' => '1.5']], [['material' => '999999999999999999999']],
            [['throwable' => true, 'weapon_type' => 'Wurfgeschoss', 'precision' => 3, 'range_increment' => 1, 'max_range' => 2, 'fire_rate' => 'E']],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_or_forged_inputs_fail(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RpgItemCreationEvaluator)->evaluate($this->input($changes));
    }
}

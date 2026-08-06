<?php

namespace Tests\Unit;

use App\Support\RpgCharEditorEquipment;
use PHPUnit\Framework\TestCase;

class RpgCharEditorEquipmentCombatProfileTest extends TestCase
{
    public function test_every_weapon_armor_and_shield_has_structured_combat_data(): void
    {
        foreach (RpgCharEditorEquipment::items() as $item) {
            $category = $item['category'];

            if (in_array($category, ['melee_weapons', 'ranged_weapons'], true)) {
                $this->assertSame('weapon', $item['combat']['kind'] ?? null, $item['name']);
                $this->assertNotEmpty($item['combat']['modes'] ?? [], $item['name']);

                foreach ($item['combat']['modes'] as $mode) {
                    $this->assertContains($mode['kind'], ['melee', 'ranged']);
                    $this->assertContains($mode['skill'], ['Nahkampf', 'Fernkampf', 'Feuerwaffen']);
                    $this->assertIsInt($mode['precision']);
                    $this->assertIsInt($mode['damage']);
                }
            }

            if ($category === 'armor') {
                $this->assertSame('armor', $item['combat']['kind'] ?? null, $item['name']);
                $this->assertIsInt($item['combat']['protection']);
                $this->assertIsInt($item['combat']['movementModifier']);
            }

            if ($category === 'shields') {
                $this->assertSame('shield', $item['combat']['kind'] ?? null, $item['name']);
                $this->assertSame(1, $item['combat']['defenseBonus'] ?? null, $item['name']);
            }
        }
    }

    public function test_thrown_melee_weapons_have_their_second_ranged_mode(): void
    {
        $profiles = RpgCharEditorEquipment::combatProfiles();

        foreach (['messer-dolch', 'axt', 'keule', 'harpoon-speer'] as $id) {
            $this->assertCount(2, $profiles[$id]['modes']);
            $this->assertSame('melee', $profiles[$id]['modes'][0]['kind']);
            $this->assertSame('ranged', $profiles[$id]['modes'][1]['kind']);
            $this->assertSame('Fernkampf', $profiles[$id]['modes'][1]['skill']);
        }
    }

    public function test_item_map_keeps_combat_profiles_and_rule_config_exposes_them(): void
    {
        $itemMap = RpgCharEditorEquipment::itemMap();
        $configMap = array_column(RpgCharEditorEquipment::ruleConfig()['items'], null, 'id');

        $this->assertSame($itemMap['bogen']['combat'], $configMap['bogen']['combat']);
        $this->assertSame('ranged', $itemMap['bogen']['combat']['modes'][0]['kind']);
        $this->assertSame(['wa'], $itemMap['bogen']['combat']['modes'][0]['attributes']);
        $this->assertSame(1, $itemMap['bogen']['combat']['modes'][0]['precision']);
        $this->assertSame(-1, $itemMap['bogen']['combat']['modes'][0]['damage']);
        $this->assertSame(3, $itemMap['kampfpanzer']['combat']['protection']);
        $this->assertSame(-1, $itemMap['kampfpanzer']['combat']['movementModifier']);
    }
}

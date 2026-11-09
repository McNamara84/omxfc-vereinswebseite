<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RpgCharEditorEquipment;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('every weapon armor and shield has structured combat data', function (): void {
    foreach (RpgCharEditorEquipment::items() as $item) {
        $category = $item['category'];
        if (in_array($category, ['melee_weapons', 'ranged_weapons'], true)) {
            expect($item['combat']['kind'] ?? null)->toBe('weapon')
                ->and($item['combat']['modes'] ?? [])->not->toBeEmpty();
            foreach ($item['combat']['modes'] as $mode) {
                expect(['melee', 'ranged'])->toContain($mode['kind'])
                    ->and(['Nahkampf', 'Fernkampf', 'Feuerwaffen'])->toContain($mode['skill'])
                    ->and($mode['precision'])->toBeInt()
                    ->and($mode['damage'])->toBeInt();
            }
        }
        if ($category === 'armor') {
            expect($item['combat']['kind'] ?? null)->toBe('armor')
                ->and($item['combat']['protection'])->toBeInt()
                ->and($item['combat']['movementModifier'])->toBeInt();
        }
        if ($category === 'shields') {
            expect($item['combat']['kind'] ?? null)->toBe('shield')
                ->and($item['combat']['defenseBonus'] ?? null)->toBe(1);
        }
    }
});

test('thrown melee weapons have their second ranged mode', function (): void {
    $profiles = RpgCharEditorEquipment::combatProfiles();
    foreach (['messer-dolch', 'axt', 'keule', 'harpoon-speer'] as $id) {
        expect($profiles[$id]['modes'])->toHaveCount(2)
            ->and($profiles[$id]['modes'][0]['kind'])->toBe('melee')
            ->and($profiles[$id]['modes'][1]['kind'])->toBe('ranged')
            ->and($profiles[$id]['modes'][1]['skill'])->toBe('Fernkampf');
    }
});

test('item map keeps combat profiles and rule config exposes them', function (): void {
    $itemMap = RpgCharEditorEquipment::itemMap();
    $configMap = array_column(RpgCharEditorEquipment::ruleConfig()['items'], null, 'id');
    expect($configMap['bogen']['combat'])->toBe($itemMap['bogen']['combat'])
        ->and($itemMap['bogen']['combat']['modes'][0]['kind'])->toBe('ranged')
        ->and($itemMap['bogen']['combat']['modes'][0]['attributes'])->toBe(['wa'])
        ->and($itemMap['bogen']['combat']['modes'][0]['precision'])->toBe(-1)
        ->and($itemMap['bogen']['combat']['modes'][0]['damage'])->toBe(1)
        ->and($itemMap['kampfpanzer']['combat']['protection'])->toBe(3)
        ->and($itemMap['kampfpanzer']['combat']['movementModifier'])->toBe(-1);
});

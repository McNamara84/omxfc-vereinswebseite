<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgItemCreationEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(RpgItemCreationRulesTestFixtures::class);
dataset('RpgItemCreationRulesTest.sizes', static fn (): array => [['small', false, 0, 1], ['medium', false, 1, 4], ['large', false, 2, 12], ['small', true, 1, 4], ['medium', true, 2, 12], ['large', true, 3, 32]]);
dataset('RpgItemCreationRulesTest.ranged', static fn (): array => [['Wurfgeschoss', 0, 0, 0], ['Projektilwaffe', 1, 0, 0], ['Schießpulverwaffe', 2, 2, 6], ['Energiewaffe', 3, 3, 50]]);
dataset('RpgItemCreationRulesTest.invalidRanged', static fn (): array => [[['precision' => -3]], [['precision' => 3]], [['fire_rate' => 'B']], [['range_increment' => 0]], [['max_range' => 9]], [['magazine' => -1]], [['weapon_type' => 'Laser']], [['weapon_type' => 'Schießpulverwaffe', 'magazine' => 0]], [['weapon_type' => 'Energiewaffe', 'magazine' => 6, 'minimum_education' => 0]], [['ammunition_id' => []]], [['weapon_type' => 'Wurfgeschoss', 'magazine' => 6]], [['ammunition_id' => '', 'ammunition_name' => '', 'ammunition_unit' => 'Schuss']]]);
dataset('RpgItemCreationRulesTest.invalid', static fn (): array => [[['damage' => 99]], [['protection' => 99]], [['category' => 'unknown']], [['material' => 1]], [['high_tech' => true, 'tech_factor' => 4]], [['tech_factor' => 2]], [['utility' => 11]], [['bucks' => -1]], [['name' => ' ']], [['description' => []]], [['reference' => '']], [['size' => 'huge']], [['hands' => 3]], [['attributes' => []]], [['attributes' => ['wi']]], [['attributes' => ['st', 'st']]], [['beautiful' => 'yes']], [['category' => 'armor', 'hands' => 1]], [['bucks' => '1.5']], [['material' => '999999999999999999999']], [['throwable' => true, 'weapon_type' => 'Wurfgeschoss', 'precision' => 3, 'range_increment' => 1, 'max_range' => 2, 'fire_rate' => 'E']]]);
test('melee size and masterwork', function (string $size, bool $masterwork, int $damage, int $tw): void {
    $result = (new RpgItemCreationEvaluator)->evaluate($this->input(compact('size', 'masterwork')));
    expect($result['definition']['combat']['modes'][0]['damage'])->toBe($damage)
        ->and($result['definition']['tw'])->toBe((string) $tw);
})->with('RpgItemCreationRulesTest.sizes');
test('ranged rules and magazine rounding', function (string $type, int $damage, int $education, int $magazine): void {
    $input = $this->input(['category' => 'ranged_weapons', 'weapon_type' => $type, 'precision' => -1, 'range_increment' => 10, 'max_range' => 50, 'fire_rate' => 'E', 'magazine' => $magazine, 'ammunition_id' => 'ammo:pfeile']);
    $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
    expect($result['combat']['modes'][0]['damage'])->toBe($damage)
        ->and($result['minimumEducation'])->toBe($education)
        ->and($result['tw'])->toBe((string) ((4 + intdiv($magazine, 5)) * ($damage + 1)))
        ->and($result['combat']['modes'][0]['skill'])->toBe($damage >= 2 ? 'Feuerwaffen' : 'Fernkampf');
})->with('RpgItemCreationRulesTest.ranged');
test('armor signed penalty then technology then utility', function (): void {
    $input = $this->input(['category' => 'armor', 'effectiveness' => 2, 'movement_modifier' => -1, 'high_tech' => true, 'tech_factor' => 5, 'utility' => 2]);
    unset($input['hands']);
    $result = (new RpgItemCreationEvaluator)->evaluate($input);
    expect($result['definition']['combat']['protection'])->toBe(3)
        ->and($result['definition']['tw'])->toBe('220');
    // (2 * 4 * 3 - 2) * 5 * 2
    expect(array_column($result['calculation'], 'value'))->toBe([1, 2, 2, 2, 2, 24, 22, 110, 220]);
});
test('shield never gets an invented material bonus', function (): void {
    $input = $this->input(['category' => 'shields', 'material' => 6]);
    unset($input['hands']);
    $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
    expect($result['combat'])->toBe(['kind' => 'shield', 'defenseBonus' => 1])
        ->and($result['tw'])->toBe('8');
});
test('combined weapon has separate damage and one weapon factor', function (): void {
    $result = (new RpgItemCreationEvaluator)->evaluate($this->input(['throwable' => true, 'weapon_type' => 'Wurfgeschoss', 'precision' => 0, 'fire_rate' => 'E', 'range_increment' => 3, 'max_range' => 15]))['definition'];
    expect(array_column($result['combat']['modes'], 'damage'))->toBe([1, 0])
        ->and($result['tw'])->toBe('10')
        ->and($result)->not->toHaveKey('ammunitionProfile');
});
test('transport values and ordinary items have no invented combat profile', function (): void {
    $input = $this->input(['category' => 'transport', 'persons_min' => 2, 'persons_max' => 4, 'protection' => 3, 'land_speed' => 50, 'water_speed' => 20, 'air_speed' => 0, 'hover' => true, 'minimum_education' => 2]);
    unset($input['hands']);
    $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
    expect($result['transport']['persons_max'])->toBe(4)
        ->and($result['transport']['hover'])->toBeTrue()
        ->and($result['summary'])->toContain('50/20/0 km/h')
        ->and($result)->not->toHaveKey('combat');
    $input = $this->input(['category' => 'high_tech', 'high_tech' => true, 'tech_factor' => 1000, 'material' => 6, 'beautiful' => true, 'rarity' => 'very_rare', 'utility' => 10]);
    unset($input['hands']);
    $result = (new RpgItemCreationEvaluator)->evaluate($input)['definition'];
    expect($result['tw'])->toBe('160000')
        ->and($result)->not->toHaveKey('combat');
});
test('invalid ranged values fail', function (array $changes): void {
    expect(fn () => (new RpgItemCreationEvaluator)->evaluate($this->input($changes + ['category' => 'ranged_weapons', 'weapon_type' => 'Projektilwaffe', 'precision' => 0, 'fire_rate' => 'E', 'range_increment' => 10, 'max_range' => 50, 'magazine' => 0, 'ammunition_id' => 'ammo:pfeile'])))->toThrow(InvalidArgumentException::class);
})->with('RpgItemCreationRulesTest.invalidRanged');
test('invalid or forged inputs fail', function (array $changes): void {
    expect(fn () => (new RpgItemCreationEvaluator)->evaluate($this->input($changes)))->toThrow(InvalidArgumentException::class);
})->with('RpgItemCreationRulesTest.invalid');

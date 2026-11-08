<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatMath;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgCombat\CombatStats;
use Illuminate\Foundation\Testing\TestCase;

function combatSnapshotFactoryTest_invalidNumbers(): array
{
    return ['null' => null, 'empty' => '', 'text' => 'six', 'array' => [], 'true' => true, 'false' => false, 'fraction' => 1.5, 'fraction string' => '1.5', 'scientific notation' => '1e1', 'trailing text' => '2abc', 'leading whitespace' => ' 0', 'trailing whitespace' => '0 ', 'newline' => "0\n", 'plus sign' => '+2', 'unicode digit' => '２', 'huge positive' => str_repeat('9', 100), 'huge negative' => '-'.str_repeat('9', 100), 'integer minimum' => PHP_INT_MIN, 'integer maximum' => PHP_INT_MAX];
}
pest()->extend(TestCase::class)->use(CombatSnapshotFactoryTestFixtures::class);
dataset('CombatSnapshotFactoryTest.validAttributes', static function (): iterable {
    foreach (['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'] as $attribute) {
        foreach ([0, '0', 2, '2', -1, '-1', -20, '-20', 20, '20', '000', '-00', '02', '-02'] as $index => $value) {
            yield $attribute.' '.$index => [$attribute, $value, (int) $value];
        }
    }
});
dataset('CombatSnapshotFactoryTest.validSkills', static function (): iterable {
    foreach ([0, '0', 2, '2', 100, '100', '02', '000', '-00', '0100'] as $index => $value) {
        yield (string) $index => [$value, (int) $value];
    }
});
dataset('CombatSnapshotFactoryTest.invalidAttributes', static function (): iterable {
    foreach (['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'] as $attribute) {
        yield $attribute.' missing' => [$attribute, null, true];
        yield $attribute.' null' => [$attribute, null];
    }
    foreach (\Tests\Unit\combatSnapshotFactoryTest_invalidNumbers() + ['below minimum' => -21, 'below minimum string' => '-21', 'above maximum' => 21, 'above maximum string' => '21'] as $label => $value) {
        yield $label => ['st', $value];
    }
});
dataset('CombatSnapshotFactoryTest.invalidSkills', static function (): iterable {
    yield 'missing' => [null, true];
    foreach (\Tests\Unit\combatSnapshotFactoryTest_invalidNumbers() + ['negative' => -1, 'negative string' => '-1', 'above maximum' => 101, 'above maximum string' => '101'] as $label => $value) {
        yield $label => [$value];
    }
});
dataset('CombatSnapshotFactoryTest.invalidStructures', static function (): iterable {
    foreach ([null, '0', 0, false, []] as $index => $value) {
        yield 'attributes '.$index => ['attributes', $value, 'Ungültiges oder fehlendes Attribut: ST'];
    }
    foreach ([null, '0', 0, false] as $index => $value) {
        yield 'skills '.$index => ['skills', $value, 'Fertigkeiten fehlen.'];
    }
    foreach ([null, 'Nahkampf', [], ['value' => 2], ['name' => null, 'value' => 2], ['name' => [], 'value' => 2]] as $index => $value) {
        yield 'skill entry '.$index => ['skills', [$value], 'Ungültige Fertigkeitswerte.'];
    }
});
dataset('CombatSnapshotFactoryTest.combatStrengths', static fn (): array => ['zero integer' => [0, 0, 3], 'zero string' => ['0', 0, 3], 'negative integer' => [-1, -1, 2], 'negative string' => ['-1', -1, 2]]);
test('attribute values are preserved as integers', function (string $attribute, int|string $value, int $expected): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload['attributes'][$attribute] = $value;
    $character->payload = $payload;
    $snapshot = (new CombatSnapshotFactory)->make($character);
    expect($snapshot['attributes'][$attribute])->toBe($expected)
        ->and($character->payload)->toBe($payload);
})->with('CombatSnapshotFactoryTest.validAttributes');
test('skill values are preserved as integers', function (int|string $value, int $expected): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload['skills'][0]['value'] = $value;
    $character->payload = $payload;
    $snapshot = (new CombatSnapshotFactory)->make($character);
    expect($snapshot['skills']['Nahkampf'])->toBe($expected)
        ->and($character->payload)->toBe($payload);
})->with('CombatSnapshotFactoryTest.validSkills');
test('invalid attributes are rejected', function (string $attribute, mixed $value, bool $missing = false): void {
    $character = $this->character();
    $payload = $character->payload;
    if ($missing) {
        unset($payload['attributes'][$attribute]);
    } else {
        $payload['attributes'][$attribute] = $value;
    }
    $character->payload = $payload;
    $this->assertInvalid($character, 'Ungültiges oder fehlendes Attribut: '.strtoupper($attribute));
})->with('CombatSnapshotFactoryTest.invalidAttributes');
test('invalid skill values are rejected', function (mixed $value, bool $missing = false): void {
    $character = $this->character();
    $payload = $character->payload;
    if ($missing) {
        unset($payload['skills'][0]['value']);
    } else {
        $payload['skills'][0]['value'] = $value;
    }
    $character->payload = $payload;
    $this->assertInvalid($character, 'Ungültige Fertigkeitswerte.');
})->with('CombatSnapshotFactoryTest.invalidSkills');
test('invalid structures produce validation errors', function (string $field, mixed $value, string $message): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload[$field] = $value;
    $character->payload = $payload;
    $this->assertInvalid($character, $message);
})->with('CombatSnapshotFactoryTest.invalidStructures');
test('missing structures are rejected before defaults are applied', function (): void {
    foreach (['attributes' => 'Ungültiges oder fehlendes Attribut: ST', 'skills' => 'Fertigkeiten fehlen.'] as $field => $message) {
        $character = $this->character();
        $payload = $character->payload;
        unset($payload[$field]);
        $character->payload = $payload;
        $this->assertInvalid($character, $message);
    }
});
test('integer string and mixed payloads have identical snapshots', function (): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload['attributes'] = ['st' => 0, 'ge' => 2, 'ro' => -1, 'wi' => 1, 'wa' => 0, 'in' => -2, 'au' => 2];
    $payload['skills'][] = ['name' => 'Athletik', 'value' => 0];
    $character->payload = $payload;
    $factory = new CombatSnapshotFactory;
    $expected = $factory->make($character);
    foreach ([false, true] as $mixed) {
        $strings = $payload;
        foreach ($strings['attributes'] as $key => $value) {
            if (! $mixed || in_array($key, ['st', 'ro', 'wi'], true)) {
                $strings['attributes'][$key] = (string) $value;
            }
        }
        $strings['skills'][0]['value'] = '02';
        $strings['skills'][1]['value'] = $mixed ? 0 : '0';
        $character->payload = $strings;
        expect($factory->make($character))->toBe($expected)
            ->and($character->payload)->toBe($strings);
    }
});
test('float values are rejected without json coercion', function (): void {
    // JSON normally turns 0.0 into 0; preserve the stored type for this regression.
    foreach ([0.0, 2.0] as $value) {
        foreach (['attributes', 'skills'] as $field) {
            $character = $this->character();
            $payload = $character->payload;
            if ($field === 'attributes') {
                $payload['attributes']['st'] = $value;
            } else {
                $payload['skills'][0]['value'] = $value;
            }
            $character->setRawAttributes(['character_name' => 'Mäc', 'payload' => json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)]);
            $this->assertInvalid($character, $field === 'attributes' ? 'Ungültiges oder fehlendes Attribut: ST' : 'Ungültige Fertigkeitswerte.');
        }
    }
});
test('characters without trained skills can use unarmed attacks', function (): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload['skills'] = [];
    $character->payload = $payload;
    $snapshot = (new CombatSnapshotFactory)->make($character);
    expect($snapshot['skills'])->toBe([])
        ->and($snapshot['weapons'])->toHaveKey('faustschlag-tritt:1');
});
test('normalized strength is used for attack and damage', function (int|string $strength, int $modifier, int $damage): void {
    $character = $this->character();
    $payload = $character->payload;
    $payload['attributes']['st'] = $strength;
    $payload['skills'][0]['value'] = '2';
    $character->payload = $payload;
    $snapshot = (new CombatSnapshotFactory)->make($character);
    $state = (new CombatEngine(new CombatDice))->start([$snapshot, $snapshot], 100, 100)['state'];
    $actor = $state['actors'][1];
    $weapon = $actor['weapons']['faustschlag-tritt:1'];
    $attack = ['weapon' => $weapon, 'mode' => $weapon['modes'][0], 'input' => [], 'distance' => 100];
    $attackModifiers = CombatStats::attack($actor, $weapon, $attack['mode'], [], 100, ['attribute' => 'st']);
    $damageModifiers = CombatStats::damage($actor, $state['actors'][2], $attack, []);
    expect($attackModifiers['Nahkampf'])->toBe(2)
        ->and($attackModifiers['ST'])->toBe($modifier)
        ->and($damageModifiers['ST'])->toBe($modifier)
        ->and(CombatMath::roll([4], $damageModifiers)['total'])->toBe($damage);
})->with('CombatSnapshotFactoryTest.combatStrengths');

<?php

namespace Tests\Unit;

use App\Models\RpgCharacter;
use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatMath;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgCombat\CombatStats;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class CombatSnapshotFactoryTest extends TestCase
{
    private const ATTRIBUTES = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

    private function character(): RpgCharacter
    {
        return new RpgCharacter([
            'character_name' => 'Mäc', 'revision' => 1,
            'payload' => [
                'character' => ['race' => 'Hydrit'],
                'attributes' => array_fill_keys(self::ATTRIBUTES, 0),
                'skills' => [['name' => 'Nahkampf', 'value' => 2]],
                'advantages' => [], 'disadvantages' => [],
                'equipment' => ['items' => []],
            ],
        ]);
    }

    #[DataProvider('validAttributes')]
    public function test_attribute_values_are_preserved_as_integers(string $attribute, int|string $value, int $expected): void
    {
        $character = $this->character();
        $payload = $character->payload;
        $payload['attributes'][$attribute] = $value;
        $character->payload = $payload;

        $snapshot = (new CombatSnapshotFactory)->make($character);

        $this->assertSame($expected, $snapshot['attributes'][$attribute]);
        $this->assertSame($payload, $character->payload);
    }

    public static function validAttributes(): iterable
    {
        foreach (self::ATTRIBUTES as $attribute) {
            foreach ([0, '0', 2, '2', -1, '-1', -20, '-20', 20, '20', '000', '-00', '02', '-02'] as $index => $value) {
                yield $attribute.' '.$index => [$attribute, $value, (int) $value];
            }
        }
    }

    #[DataProvider('validSkills')]
    public function test_skill_values_are_preserved_as_integers(int|string $value, int $expected): void
    {
        $character = $this->character();
        $payload = $character->payload;
        $payload['skills'][0]['value'] = $value;
        $character->payload = $payload;

        $snapshot = (new CombatSnapshotFactory)->make($character);

        $this->assertSame($expected, $snapshot['skills']['Nahkampf']);
        $this->assertSame($payload, $character->payload);
    }

    public static function validSkills(): iterable
    {
        foreach ([0, '0', 2, '2', 100, '100', '02', '000', '-00', '0100'] as $index => $value) {
            yield (string) $index => [$value, (int) $value];
        }
    }

    #[DataProvider('invalidAttributes')]
    public function test_invalid_attributes_are_rejected(string $attribute, mixed $value, bool $missing = false): void
    {
        $character = $this->character();
        $payload = $character->payload;
        if ($missing) {
            unset($payload['attributes'][$attribute]);
        } else {
            $payload['attributes'][$attribute] = $value;
        }
        $character->payload = $payload;

        $this->assertInvalid($character, 'Ungültiges oder fehlendes Attribut: '.strtoupper($attribute));
    }

    public static function invalidAttributes(): iterable
    {
        foreach (self::ATTRIBUTES as $attribute) {
            yield $attribute.' missing' => [$attribute, null, true];
            yield $attribute.' null' => [$attribute, null];
        }
        foreach (self::invalidNumbers() + [
            'below minimum' => -21, 'below minimum string' => '-21',
            'above maximum' => 21, 'above maximum string' => '21',
        ] as $label => $value) {
            yield $label => ['st', $value];
        }
    }

    #[DataProvider('invalidSkills')]
    public function test_invalid_skill_values_are_rejected(mixed $value, bool $missing = false): void
    {
        $character = $this->character();
        $payload = $character->payload;
        if ($missing) {
            unset($payload['skills'][0]['value']);
        } else {
            $payload['skills'][0]['value'] = $value;
        }
        $character->payload = $payload;

        $this->assertInvalid($character, 'Ungültige Fertigkeitswerte.');
    }

    public static function invalidSkills(): iterable
    {
        yield 'missing' => [null, true];
        foreach (self::invalidNumbers() + [
            'negative' => -1, 'negative string' => '-1',
            'above maximum' => 101, 'above maximum string' => '101',
        ] as $label => $value) {
            yield $label => [$value];
        }
    }

    private static function invalidNumbers(): array
    {
        return [
            'null' => null, 'empty' => '', 'text' => 'six', 'array' => [],
            'true' => true, 'false' => false,
            'fraction' => 1.5, 'fraction string' => '1.5',
            'scientific notation' => '1e1', 'trailing text' => '2abc',
            'leading whitespace' => ' 0', 'trailing whitespace' => '0 ', 'newline' => "0\n",
            'plus sign' => '+2', 'unicode digit' => '２',
            'huge positive' => str_repeat('9', 100), 'huge negative' => '-'.str_repeat('9', 100),
            'integer minimum' => PHP_INT_MIN, 'integer maximum' => PHP_INT_MAX,
        ];
    }

    #[DataProvider('invalidStructures')]
    public function test_invalid_structures_produce_validation_errors(string $field, mixed $value, string $message): void
    {
        $character = $this->character();
        $payload = $character->payload;
        $payload[$field] = $value;
        $character->payload = $payload;

        $this->assertInvalid($character, $message);
    }

    public static function invalidStructures(): iterable
    {
        foreach ([null, '0', 0, false, []] as $index => $value) {
            yield 'attributes '.$index => ['attributes', $value, 'Ungültiges oder fehlendes Attribut: ST'];
        }
        foreach ([null, '0', 0, false] as $index => $value) {
            yield 'skills '.$index => ['skills', $value, 'Fertigkeiten fehlen.'];
        }
        foreach ([null, 'Nahkampf', [], ['value' => 2], ['name' => null, 'value' => 2], ['name' => [], 'value' => 2]] as $index => $value) {
            yield 'skill entry '.$index => ['skills', [$value], 'Ungültige Fertigkeitswerte.'];
        }
    }

    public function test_missing_structures_are_rejected_before_defaults_are_applied(): void
    {
        foreach (['attributes' => 'Ungültiges oder fehlendes Attribut: ST', 'skills' => 'Fertigkeiten fehlen.'] as $field => $message) {
            $character = $this->character();
            $payload = $character->payload;
            unset($payload[$field]);
            $character->payload = $payload;
            $this->assertInvalid($character, $message);
        }
    }

    public function test_integer_string_and_mixed_payloads_have_identical_snapshots(): void
    {
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

            $this->assertSame($expected, $factory->make($character));
            $this->assertSame($strings, $character->payload);
        }
    }

    public function test_float_values_are_rejected_without_json_coercion(): void
    {
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
    }

    public function test_characters_without_trained_skills_can_use_unarmed_attacks(): void
    {
        $character = $this->character();
        $payload = $character->payload;
        $payload['skills'] = [];
        $character->payload = $payload;

        $snapshot = (new CombatSnapshotFactory)->make($character);

        $this->assertSame([], $snapshot['skills']);
        $this->assertArrayHasKey('faustschlag-tritt:1', $snapshot['weapons']);
    }

    #[DataProvider('combatStrengths')]
    public function test_normalized_strength_is_used_for_attack_and_damage(int|string $strength, int $modifier, int $damage): void
    {
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

        $this->assertSame(2, $attackModifiers['Nahkampf']);
        $this->assertSame($modifier, $attackModifiers['ST']);
        $this->assertSame($modifier, $damageModifiers['ST']);
        $this->assertSame($damage, CombatMath::roll([4], $damageModifiers)['total']);
    }

    public static function combatStrengths(): array
    {
        return [
            'zero integer' => [0, 0, 3], 'zero string' => ['0', 0, 3],
            'negative integer' => [-1, -1, 2], 'negative string' => ['-1', -1, 2],
        ];
    }

    private function assertInvalid(RpgCharacter $character, string $message): void
    {
        $payload = $character->payload;
        try {
            (new CombatSnapshotFactory)->make($character);
            $this->fail('Expected invalid character values to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['character' => [$message]], $exception->errors());
            $this->assertSame($payload, $character->payload);
        }
    }
}

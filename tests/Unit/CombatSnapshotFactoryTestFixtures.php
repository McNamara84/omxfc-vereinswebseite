<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\RpgCharacter;
use App\Services\RpgCombat\CombatSnapshotFactory;
use Illuminate\Validation\ValidationException;

trait CombatSnapshotFactoryTestFixtures
{
    private const ATTRIBUTES = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

    private function character(): RpgCharacter
    {
        return new RpgCharacter(['character_name' => 'Mäc', 'revision' => 1, 'payload' => ['character' => ['race' => 'Hydrit'], 'attributes' => array_fill_keys(self::ATTRIBUTES, 0), 'skills' => [['name' => 'Nahkampf', 'value' => 2]], 'advantages' => [], 'disadvantages' => [], 'equipment' => ['items' => []]]]);
    }

    private function assertInvalid(RpgCharacter $character, string $message): void
    {
        $payload = $character->payload;
        try {
            (new CombatSnapshotFactory)->make($character);
            $this->fail('Expected invalid character values to be rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['character' => [$message]])
                ->and($character->payload)->toBe($payload);
        }
    }
}

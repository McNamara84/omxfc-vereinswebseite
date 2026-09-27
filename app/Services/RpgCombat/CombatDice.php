<?php

namespace App\Services\RpgCombat;

class CombatDice
{
    public function die(): int
    {
        return random_int(1, 6);
    }

    public function roll(int $count = 2): array
    {
        if (! in_array($count, [1, 2], true)) {
            throw new \InvalidArgumentException('Ein oder zwei W6 erforderlich.');
        }

        return array_map(fn () => $this->die(), range(1, $count));
    }

    public function w66(): array
    {
        $dice = $this->roll();

        return ['dice' => $dice, 'total' => 10 * $dice[0] + $dice[1]];
    }
}

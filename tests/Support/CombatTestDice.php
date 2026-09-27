<?php

namespace Tests\Support;

use App\Services\RpgCombat\CombatDice;

class CombatTestDice extends CombatDice
{
    public array $values = [];

    public int $calls = 0;

    public function die(): int
    {
        $this->calls++;

        return array_shift($this->values) ?? 4;
    }
}

<?php

namespace App\Services;

class RpgCheckDice
{
    public function roll(): array
    {
        return [random_int(1, 6), random_int(1, 6)];
    }
}

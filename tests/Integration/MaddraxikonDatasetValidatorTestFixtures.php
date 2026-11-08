<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonDatasetValidatorTestFixtures
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function row(int $number, ?string $cycle): array
    {
        return ['nummer' => $number, 'titel' => "Roman {$number}", 'zyklus' => $cycle, 'text' => ['Autor']];
    }
}

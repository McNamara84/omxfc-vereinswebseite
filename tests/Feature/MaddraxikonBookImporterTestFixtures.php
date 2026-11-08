<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonBookImporterTestFixtures
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function row(int $number, ?string $pageTitle): array
    {
        return ['nummer' => $number, 'titel' => "Hardcover {$number}", 'text' => ['Autor'], 'zyklus' => null, 'maddraxikon_seitentitel' => $pageTitle];
    }
}

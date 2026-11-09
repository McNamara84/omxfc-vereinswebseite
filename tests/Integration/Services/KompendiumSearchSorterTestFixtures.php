<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\KompendiumRoman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KompendiumSearchSorterTestFixtures
{
    use RefreshDatabase;

    private function createRoman(User $user, string $path, string $serie, int $romanNr, string $titel, ?string $erstveroeffentlichtAm = null): KompendiumRoman
    {
        return KompendiumRoman::create(['dateiname' => basename($path), 'dateipfad' => $path, 'serie' => $serie, 'roman_nr' => $romanNr, 'titel' => $titel, 'erstveroeffentlicht_am' => $erstveroeffentlichtAm, 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    }
}

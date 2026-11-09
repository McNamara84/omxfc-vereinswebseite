<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumSearchTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::where('slug', 'kompendium')->firstOrFail();
        RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    }

    private function createIndexedSearchRoman(User $user, string $path, string $content, string $serie, int $romanNr, string $titel, ?string $erstveroeffentlichtAm = null): void
    {
        Storage::disk('private')->put($path, $content);
        KompendiumRoman::create(['dateiname' => basename($path), 'dateipfad' => $path, 'serie' => $serie, 'roman_nr' => $romanNr, 'titel' => $titel, 'erstveroeffentlicht_am' => $erstveroeffentlichtAm, 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    }
}

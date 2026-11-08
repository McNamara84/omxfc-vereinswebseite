<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumSearchLoggingTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::query()->where('slug', 'kompendium')->firstOrFail();
        RewardPurchase::query()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    }

    private function createIndexedRoman(User $user, string $path, string $content, string $serie = 'maddrax', int $number = 1): void
    {
        Storage::disk('private')->put($path, $content);
        KompendiumRoman::query()->create(['dateiname' => basename($path), 'dateipfad' => $path, 'serie' => $serie, 'roman_nr' => $number, 'titel' => pathinfo($path, PATHINFO_FILENAME), 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    }

    private function mockSearch(array $paths, int $times = 1): void
    {
        $this->partialMock(KompendiumSearchService::class, function ($mock) use ($paths, $times) {
            $mock->shouldReceive('search')->times($times)->andReturn(['ids' => $paths]);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumControllerTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * Erstellt eine AG Maddraxikon und fügt den User als Mitglied hinzu.
     */
    private function addUserToAgMaddraxikon(User $user): Team
    {
        $ag = Team::factory()->create(['name' => 'AG Maddraxikon', 'personal_team' => false]);
        $ag->users()->attach($user, ['role' => Role::Mitglied->value]);
        $user->refresh();

        return $ag;
    }

    /**
     * Kauft das Kompendium-Reward für einen User.
     */
    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::where('slug', 'kompendium')->firstOrFail();
        RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    }

    private function purchaseLegacyKompendiumForUser(User $user): void
    {
        $reward = Reward::updateOrCreate(['slug' => 'kompendium-suche'], ['title' => 'Kompendium-Suche', 'description' => 'Legacy-Zugang zur Kompendium-Suche.', 'category' => 'Kompendium', 'cost_baxx' => 100, 'is_active' => true, 'sort_order' => 0]);
        RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    }

    /* --------------------------------------------------------------------- */
    /*  Phrasensuche – Feature-Tests */
    /* --------------------------------------------------------------------- */
    /**
     * Bereitet den Fake-Storage und einen SearchService-Mock vor.
     *
     * @param  array<string, string>  $files  Pfad → Inhalt (z.B. 'romane/maddrax/001 - Test.txt' => 'Text...')
     * @param  list<string>  $searchResultPaths  Die Pfade, die TNTSearch als Treffer liefern soll
     */
    private function setupSearchMock(array $files, array $searchResultPaths): void
    {
        Storage::fake('private');
        foreach ($files as $path => $content) {
            Storage::disk('private')->put($path, $content);
        }
        $this->partialMock(KompendiumSearchService::class, function ($mock) use ($searchResultPaths) {
            $mock->shouldReceive('search')->andReturn(['ids' => $searchResultPaths]);
        });
    }
}

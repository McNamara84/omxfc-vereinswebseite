<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class KompendiumPageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::where('slug', 'kompendium')->firstOrFail();

        RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => $reward->cost_baxx,
            'purchased_at' => now(),
        ]);
    }

    public function test_kompendium_page_shows_context_panels_for_members(): void
    {
        $this->actingMember();

        $response = $this->withoutVite()->get('/kompendium');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSeeText('Maddrax-Kompendium');
        $response->assertSeeText('Indexierte Serien');
        $response->assertSeeText('Aktueller Stand');
        $response->assertSee('data-testid="kompendium-primary-access"', false);
        $response->assertSee('data-testid="kompendium-access-help-button"', false);
        $this->assertIsInt(strpos($html, 'data-testid="kompendium-primary-access"'));
        $this->assertIsInt(strpos($html, 'Indexierte Serien'));
        $this->assertLessThan(
            strpos($html, 'Indexierte Serien'),
            strpos($html, 'data-testid="kompendium-primary-access"')
        );
    }

    public function test_unlocked_kompendium_page_places_search_before_secondary_panels(): void
    {
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        $response = $this->withoutVite()->get('/kompendium');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSeeLivewire('kompendium-suche');
        $response->assertSee('data-testid="kompendium-primary-search"', false);
        $response->assertSee('data-testid="kompendium-search-help-button"', false);
        $this->assertIsInt(strpos($html, 'data-testid="kompendium-primary-search"'));
        $this->assertIsInt(strpos($html, 'Indexierte Serien'));
        $this->assertLessThan(
            strpos($html, 'Indexierte Serien'),
            strpos($html, 'data-testid="kompendium-primary-search"')
        );
    }
}
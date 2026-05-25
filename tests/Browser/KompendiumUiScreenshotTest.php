<?php

use App\Enums\Role;
use App\Models\KompendiumRoman;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use App\Support\TestingBladeComponentRegistry;
use Mary\MaryServiceProvider;

it('erstellt einen screenshot der neuen kompendium ui', function () {
    $createKompendiumBrowserUser = function (int $points): User {
        $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder']);

        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $this->actingAs($user->refresh());

        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }

        return $user->refresh();
    };

    $purchaseKompendiumForBrowserUser = static function (User $user): void {
        $reward = Reward::query()->where('slug', 'kompendium')->firstOrFail();

        RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => $reward->cost_baxx,
            'purchased_at' => now(),
        ]);
    };

    try {
        (new MaryServiceProvider(app()))->registerComponents();

        $user = $createKompendiumBrowserUser(150);

        $purchaseKompendiumForBrowserUser($user);

        KompendiumRoman::create([
            'dateiname' => '001 - Die Ruinenstadt.txt',
            'dateipfad' => 'romane/maddrax/001 - Die Ruinenstadt.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Die Ruinenstadt',
            'zyklus' => 'Euree',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '001 - Aufbruch zum Mars.txt',
            'dateipfad' => 'romane/missionmars/001 - Aufbruch zum Mars.txt',
            'serie' => 'missionmars',
            'roman_nr' => 1,
            'titel' => 'Aufbruch zum Mars',
            'zyklus' => null,
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        $page = visit('/kompendium', ['waitUntil' => 'domcontentloaded'])
            ->resize(1440, 1800)
            ->waitForText('Serien filtern');

        $page->assertPathIs('/kompendium')
            ->assertSee('Maddrax-Kompendium')
            ->assertVisible('[data-testid="kompendium-primary-search"]')
            ->assertVisible('[data-testid="kompendium-search"]')
            ->assertVisible('[data-testid="kompendium-search-help-button"]')
            ->assertVisible('[data-testid="kompendium-filter-help-button"]')
            ->assertScript("document.querySelector('[data-testid=\"kompendium-search-help-button\"]')?.className.includes('btn')", true)
            ->assertNoJavaScriptErrors()
            ->screenshotElement('main', 'kompendium-ui');
    } finally {
        TestingBladeComponentRegistry::register();
    }
});
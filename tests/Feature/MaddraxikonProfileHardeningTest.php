<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Profile\MaddraxikonAccountPanel;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaddraxikonProfileHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.features.linking_enabled' => true,
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
        ]);
    }

    public function test_ineligible_owner_still_sees_disconnect_for_active_link(): void
    {
        $formerMember = $this->createMember(Role::Anwaerter);
        MaddraxikonAccountLink::factory()->for($formerMember)->create([
            'wiki_username' => 'Eigenes Wiki-Konto',
        ]);

        Livewire::actingAs($formerMember)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('Eigenes Wiki-Konto')
            ->assertSee('Verbindung trennen')
            ->assertSee('Vereinsberechtigung ist derzeit nicht aktiv')
            ->assertDontSee('Mit Maddraxikon verbinden');
    }

    public function test_panel_reads_current_reward_rules_and_policy_configuration(): void
    {
        $member = $this->createMember();
        MaddraxikonAccountLink::factory()->for($member)->create();

        BaxxEarningRule::query()
            ->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)
            ->update([
                'points' => 3,
                'every_count' => 7,
                'is_active' => true,
            ]);
        BaxxEarningRule::query()
            ->where('action_key', MaddraxikonRewardEvent::ACTION_NEW_ARTICLE)
            ->update([
                'points' => 9,
                'is_active' => true,
            ]);
        config([
            'maddraxikon.evaluation_delay_hours' => 12,
            'maddraxikon.minimum_article_bytes' => 700,
            'maddraxikon.daily_point_cap' => 14,
        ]);

        Livewire::actingAs($member)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('Änderungen werden nach 12 Stunden geprüft.')
            ->assertSee('7 qualifizierte Bearbeitungssitzungen ergeben 3 Baxx.')
            ->assertSee('Ein neuer Artikel mit mindestens 700 Byte ergibt 9 Baxx.')
            ->assertSee('höchstens 14 Baxx gutgeschrieben.')
            ->assertDontSee('mindestens 500 Byte ergibt 5 Baxx');
    }

    public function test_profile_tour_contains_maddraxikon_panel_anchor(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('data-tour-profile-key="profile-maddraxikon-baxx"', escape: false);

        $step = collect(config('tours.profilpflege.steps'))
            ->firstWhere('key', 'profile-maddraxikon-baxx');

        $this->assertSame(2, config('tours.profilpflege.version'));
        $this->assertSame(
            '[data-tour-profile-key="profile-maddraxikon-baxx"]',
            $step['selectors']['desktop'] ?? null,
        );
    }

    public function test_panel_displays_current_byte_tiers_and_upcoming_change(): void
    {
        $member = $this->createMember();
        MaddraxikonAccountLink::factory()->for($member)->create();
        $current = MaddraxikonRewardPolicy::factory()->create([
            'name' => 'Aktuelle Staffel',
            'effective_from' => now()->subDay(),
            'new_article_minimum_bytes' => 800,
            'new_article_points' => 6,
        ]);
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $current->id,
            'minimum_added_bytes' => 250,
            'points' => 2,
        ]);
        $current->update([
            'status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $next = MaddraxikonRewardPolicy::factory()->create([
            'name' => 'Winterstaffel',
            'effective_from' => now()->addDay(),
        ]);
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $next->id,
            'minimum_added_bytes' => 500,
            'points' => 3,
        ]);
        $next->update([
            'status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Livewire::actingAs($member)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('30-Minuten-Sitzungen')
            ->assertSee('ab 250 Bytes 2 Baxx')
            ->assertSee('mindestens 800 Byte ergibt 6 Baxx')
            ->assertSee('Winterstaffel')
            ->assertDontSee('qualifizierte Bearbeitungssitzungen ergeben');
    }

    private function createMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create([
            'current_team_id' => $team->id,
            'lat' => 48.0,
            'lon' => 11.0,
        ]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}

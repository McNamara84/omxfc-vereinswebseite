<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Profile\MaddraxikonAccountPanel;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonProfileHardeningTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.features.linking_enabled' => true, 'maddraxikon.base_url' => 'https://de.maddraxikon.com']);
});

test('ineligible owner still sees disconnect for active link', function (): void {
    $formerMember = $this->createMember(Role::Anwaerter);
    MaddraxikonAccountLink::factory()->for($formerMember)->create(['wiki_username' => 'Eigenes Wiki-Konto']);
    Livewire::actingAs($formerMember)->test(MaddraxikonAccountPanel::class)->assertSee('Eigenes Wiki-Konto')->assertSee('Verbindung trennen')->assertSee('Vereinsberechtigung ist derzeit nicht aktiv')->assertDontSee('Mit Maddraxikon verbinden');
});

test('panel reads current reward rules and policy configuration', function (): void {
    $member = $this->createMember();
    MaddraxikonAccountLink::factory()->for($member)->create();
    BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)->update(['points' => 3, 'every_count' => 7, 'is_active' => true]);
    BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_NEW_ARTICLE)->update(['points' => 9, 'is_active' => true]);
    config(['maddraxikon.evaluation_delay_hours' => 12, 'maddraxikon.minimum_article_bytes' => 700, 'maddraxikon.daily_point_cap' => 14]);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('Änderungen werden nach 12 Stunden geprüft.')->assertSee('7 qualifizierte Bearbeitungssitzungen ergeben 3 Baxx.')->assertSee('Ein neuer Artikel mit mindestens 700 Byte ergibt 9 Baxx.')->assertSee('höchstens 14 reguläre Beitrags-Baxx gutgeschrieben.')->assertSee('Serienbonus kommt zusätzlich hinzu.')->assertDontSee('mindestens 500 Byte ergibt 5 Baxx');
});

test('profile tour contains maddraxikon panel anchor', function (): void {
    $member = $this->createMember();
    $this->actingAs($member)->get(route('profile.show'))->assertOk()->assertSee('data-tour-profile-key="profile-maddraxikon-baxx"', escape: false);
    $step = collect(config('tours.profilpflege.steps'))->firstWhere('key', 'profile-maddraxikon-baxx');
    expect(config('tours.profilpflege.version'))->toBe(3)
        ->and($step['selectors']['desktop'] ?? null)->toBe('[data-tour-profile-key="profile-maddraxikon-baxx"]');
});

test('panel displays current byte tiers and upcoming change', function (): void {
    $member = $this->createMember();
    MaddraxikonAccountLink::factory()->for($member)->create();
    $current = MaddraxikonRewardPolicy::factory()->create(['name' => 'Aktuelle Staffel', 'effective_from' => now()->subDay(), 'new_article_minimum_bytes' => 800, 'new_article_points' => 6]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $current->id, 'minimum_added_bytes' => 250, 'points' => 2]);
    $current->update(['status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);
    $next = MaddraxikonRewardPolicy::factory()->create(['name' => 'Winterstaffel', 'effective_from' => now()->addDay(), 'new_article_minimum_bytes' => 1200, 'new_article_points' => 8]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $next->id, 'minimum_added_bytes' => 500, 'points' => 3]);
    $next->update(['status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED, 'published_at' => now()]);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('30-Minuten-Sitzungen')->assertSee('ab 250 Bytes 2 Baxx')->assertSee('mindestens 800 Byte ergibt 6 Baxx')->assertSee('Winterstaffel')->assertSee('ab 500 Bytes 3 Baxx')->assertSee('Neue Artikel erhalten dann ab 1.200 Byte 8 Baxx')->assertDontSee('qualifizierte Bearbeitungssitzungen ergeben');
});

test('panel translates internal reward reasons for members', function (): void {
    $member = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->for($member)->create();
    $reasons = ['below_minimum_edit_size' => 'Netto-Zuwachs unter der kleinsten Stufe', 'daily_cap_partially_applied' => 'Durch den Tageshöchstwert teilweise gutgeschrieben', 'new_articles_disabled' => 'Neue Artikel waren nicht aktiviert'];
    foreach ($reasons as $reason => $label) {
        $contribution = MaddraxikonContribution::factory()->for($member)->create(['account_link_id' => $link->id]);
        MaddraxikonRewardEvent::factory()->for($member)->create(['account_link_id' => $link->id, 'source_contribution_id' => $contribution->id, 'status_reason' => $reason]);
    }
    $component = Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class);
    foreach ($reasons as $reason => $label) {
        $component->assertSee($label)->assertDontSee($reason);
    }
});

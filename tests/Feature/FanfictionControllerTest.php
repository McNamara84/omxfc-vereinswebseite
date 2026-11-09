<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FanfictionControllerTestFixtures::class);

beforeEach(function (): void {
    $this->memberTeam = Team::membersTeam();
    $this->member = User::factory()->create();
    $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $this->member->switchTeam($this->memberTeam);
    $this->anwaerter = User::factory()->create();
    $this->anwaerter->teams()->attach($this->memberTeam, ['role' => Role::Anwaerter->value]);
    $this->anwaerter->switchTeam($this->memberTeam);
});

test('guest can access public teaser page', function (): void {
    Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $response = $this->get(route('fanfiction.public'));
    $response->assertOk();
    $response->assertViewIs('fanfiction.public-index');
});

test('public page only shows published fanfictions', function (): void {
    Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id, 'title' => 'Veröffentlichte Geschichte']);
    Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id, 'title' => 'Entwurf Geschichte']);
    $response = $this->get(route('fanfiction.public'));
    $response->assertOk();
    $response->assertSee('Veröffentlichte Geschichte');
    $response->assertDontSee('Entwurf Geschichte');
});

test('member can access fanfiction index', function (): void {
    $response = $this->actingAs($this->member)->get(route('fanfiction.index'));
    $response->assertOk();
    $response->assertViewIs('fanfiction.index');
});

test('anwaerter cannot access fanfiction index', function (): void {
    $response = $this->actingAs($this->anwaerter)->get(route('fanfiction.index'));
    // Anwärter werden zur Freischaltungsseite redirected
    $response->assertRedirect();
});

test('member can view published fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    $response->assertViewIs('fanfiction.show');
    $response->assertSee($fanfiction->title);
});

test('member pages use nicknames while public teaser keeps name', function (): void {
    $this->member->update(['name' => 'Mara Mitglied', 'alias' => 'MaddraxMara']);
    $commenter = User::factory()->create(['name' => 'Karla Kommentar', 'alias' => 'KommentarKarla']);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Nickname-Geschichte']);
    FanfictionComment::factory()->create(['fanfiction_id' => $fanfiction->id, 'user_id' => $commenter->id, 'content' => 'Kommentar mit Nickname']);
    $this->get(route('fanfiction.public'))->assertOk()->assertSee('Mara Mitglied')->assertDontSee('MaddraxMara');
    $this->actingAs($this->member)->get(route('fanfiction.index'))->assertOk()->assertSee('MaddraxMara')->assertSee('KommentarKarla');
    $this->get(route('fanfiction.show', $fanfiction))->assertOk()->assertSee('MaddraxMara')->assertSee('KommentarKarla');
});

test('member page keeps external author name', function (): void {
    $fanfiction = Fanfiction::factory()->published()->externalAuthor()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id, 'author_name' => 'Externe Autorin']);
    $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction))->assertOk()->assertSee('Externe Autorin');
});

test('member cannot view draft fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction));
    $response->assertNotFound();
});

test('vorstand can view draft fanfiction', function (): void {
    $vorstand = User::factory()->create();
    $vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
    $vorstand->switchTeam($this->memberTeam);
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $vorstand->id]);
    $response = $this->actingAs($vorstand)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
});

test('fanfiction shows comment count', function (): void {
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    FanfictionComment::factory()->count(3)->create(['fanfiction_id' => $fanfiction->id, 'user_id' => $this->member->id]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.index'));
    $response->assertOk();
    $response->assertSee('3');
});

test('member can purchase fanfiction with reward', function (): void {
    $this->member->incrementTeamPoints(20);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $reward = $this->createRewardForFanfiction($fanfiction);
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $fanfiction));
    $response->assertRedirect(route('fanfiction.show', $fanfiction));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5]);
});

test('author can view own rewarded fanfiction without purchase', function (): void {
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Eigene verriegelte Geschichte']);
    $this->createRewardForFanfiction($fanfiction, 25);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    $response->assertSee('Eigener Beitrag');
    $response->assertDontSee('Diese Geschichte ist gesperrt');
});

test('show reloads wallet state after auto refund for own fanfiction', function (): void {
    $this->member->incrementTeamPoints(10);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Eigene Geschichte mit Erstattung in Detailansicht']);
    $reward = $this->createRewardForFanfiction($fanfiction, 5);
    $purchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    $response->assertSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');
    $response->assertViewHas('autoRefundedPurchases', 1);
    $response->assertViewHas('availableBaxx', 10);
    $purchase->refresh();
    expect($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->refunded_by)->toBeNull();
});

test('creator without linked author must still unlock rewarded fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->published()->externalAuthor()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id, 'title' => 'Nicht technisch verknüpfte Geschichte']);
    $this->createRewardForFanfiction($fanfiction, 25);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    $response->assertSee('Diese Geschichte ist gesperrt');
    $response->assertDontSee('Eigener Beitrag');
});

test('showing foreign fanfiction does not refund unrelated own purchases', function (): void {
    $ownFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Eigene Geschichte mit Alt-Kauf']);
    $ownReward = $this->createRewardForFanfiction($ownFanfiction, 5);
    $purchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $ownReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $otherAuthor = User::factory()->create();
    $foreignFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $otherAuthor->id, 'created_by' => $otherAuthor->id, 'title' => 'Fremde Geschichte']);
    $this->createRewardForFanfiction($foreignFanfiction, 5);
    $response = $this->actingAs($this->member)->get(route('fanfiction.show', $foreignFanfiction));
    $response->assertOk();
    $response->assertDontSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');
    $purchase->refresh();
    expect($purchase->refunded_at)->toBeNull();
});

test('fanfiction index refunds existing self purchases automatically', function (): void {
    $this->member->incrementTeamPoints(40);
    $firstFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Erste eigene Geschichte']);
    $secondFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Zweite eigene Geschichte']);
    $firstReward = $this->createRewardForFanfiction($firstFanfiction, 5);
    $secondReward = $this->createRewardForFanfiction($secondFanfiction, 7);
    $firstPurchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $firstReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $secondPurchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $secondReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 7, 'purchased_at' => now()]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.index'));
    $response->assertOk();
    $response->assertSee('2 frühere Eigenkäufe deiner Fanfiction wurden automatisch erstattet.');
    $response->assertSee('40 Baxx verfügbar');
    $response->assertSee('Eigener Beitrag');
    expect($firstPurchase->fresh()->refunded_at)->not->toBeNull()
        ->and($secondPurchase->fresh()->refunded_at)->not->toBeNull()
        ->and($firstPurchase->fresh()->updated_at?->equalTo($firstPurchase->fresh()->refunded_at))->toBeTrue()
        ->and($secondPurchase->fresh()->updated_at?->equalTo($secondPurchase->fresh()->refunded_at))->toBeTrue()
        ->and($firstPurchase->fresh()->refunded_by)->toBeNull()
        ->and($secondPurchase->fresh()->refunded_by)->toBeNull();
});

test('fanfiction index refunds self purchase for soft deleted own fanfiction', function (): void {
    $this->member->incrementTeamPoints(10);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Gelöschte eigene Geschichte']);
    $reward = $this->createRewardForFanfiction($fanfiction, 5);
    $purchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $fanfiction->delete();
    $response = $this->actingAs($this->member)->get(route('fanfiction.index'));
    $response->assertOk();
    $response->assertSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');
    $response->assertSee('10 Baxx verfügbar');
    $purchase->refresh();
    expect($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->refunded_by)->toBeNull();
});

test('purchase route does not charge for own fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id]);
    $reward = $this->createRewardForFanfiction($fanfiction, 9);
    $purchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 9, 'purchased_at' => now()]);
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $fanfiction));
    $response->assertRedirect(route('fanfiction.show', $fanfiction));
    $response->assertSessionHas('info', 'Deine eigene Fanfiction ist bereits freigeschaltet. Frühere Eigenkäufe wurden automatisch erstattet.');
    $purchase->refresh();
    expect($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->refunded_by)->toBeNull()
        ->and(RewardPurchase::where('user_id', $this->member->id)->count())->toBe(1);
});

test('purchasing foreign fanfiction does not refund unrelated own purchases', function (): void {
    $this->member->incrementTeamPoints(20);
    $ownFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Eigene Geschichte mit Alt-Kauf']);
    $ownReward = $this->createRewardForFanfiction($ownFanfiction, 5);
    $ownPurchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $ownReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $otherAuthor = User::factory()->create();
    $foreignFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $otherAuthor->id, 'created_by' => $otherAuthor->id, 'title' => 'Fremde Kaufgeschichte']);
    $foreignReward = $this->createRewardForFanfiction($foreignFanfiction, 5);
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $foreignFanfiction));
    $response->assertRedirect(route('fanfiction.show', $foreignFanfiction));
    $response->assertSessionHas('success');
    $ownPurchase->refresh();
    expect($ownPurchase->refunded_at)->toBeNull();
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $this->member->id, 'reward_id' => $foreignReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => 5]);
});

test('fanfiction index shows wallet warning for ambiguous legacy purchase', function (): void {
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'fanfiction-legacy-warning']);
    RewardPurchase::factory()->create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5]);
    $response = $this->actingAs($this->member)->get(route('fanfiction.index'));
    $response->assertOk();
    $response->assertSee('Baxx-Guthaben wird geprüft');
});

test('purchase with insufficient baxx fails', function (): void {
    // Member hat 0 Baxx
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $reward = $this->createRewardForFanfiction($fanfiction, 50);
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $fanfiction));
    $response->assertRedirect(route('fanfiction.show', $fanfiction));
    $response->assertSessionHasErrors(['reward']);
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $reward->id]);
});

test('double purchase is rejected', function (): void {
    $this->member->incrementTeamPoints(100);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $reward = $this->createRewardForFanfiction($fanfiction);
    // Erster Kauf
    RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    // Zweiter Kauf wird abgelehnt
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $fanfiction));
    $response->assertRedirect(route('fanfiction.show', $fanfiction));
    $response->assertSessionHasErrors(['reward']);
});

test('purchase unpublished fanfiction returns 404', function (): void {
    $this->member->incrementTeamPoints(20);
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
    $this->createRewardForFanfiction($fanfiction);
    $response = $this->actingAs($this->member)->post(route('fanfiction.purchase', $fanfiction));
    $response->assertNotFound();
});

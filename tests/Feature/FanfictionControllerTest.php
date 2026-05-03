<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FanfictionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $member;

    private User $anwaerter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberTeam = Team::membersTeam();

        $this->member = User::factory()->create();
        $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $this->member->switchTeam($this->memberTeam);

        $this->anwaerter = User::factory()->create();
        $this->anwaerter->teams()->attach($this->memberTeam, ['role' => Role::Anwaerter->value]);
        $this->anwaerter->switchTeam($this->memberTeam);
    }

    public function test_guest_can_access_public_teaser_page(): void
    {
        Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);

        $response = $this->get(route('fanfiction.public'));

        $response->assertOk();
        $response->assertViewIs('fanfiction.public-index');
    }

    public function test_public_page_only_shows_published_fanfictions(): void
    {
        Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
            'title' => 'Veröffentlichte Geschichte',
        ]);

        Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
            'title' => 'Entwurf Geschichte',
        ]);

        $response = $this->get(route('fanfiction.public'));

        $response->assertOk();
        $response->assertSee('Veröffentlichte Geschichte');
        $response->assertDontSee('Entwurf Geschichte');
    }

    public function test_member_can_access_fanfiction_index(): void
    {
        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.index'));

        $response->assertOk();
        $response->assertViewIs('fanfiction.index');
    }

    public function test_anwaerter_cannot_access_fanfiction_index(): void
    {
        $response = $this->actingAs($this->anwaerter)
            ->get(route('fanfiction.index'));

        // Anwärter werden zur Freischaltungsseite redirected
        $response->assertRedirect();
    }

    public function test_member_can_view_published_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        $response->assertViewIs('fanfiction.show');
        $response->assertSee($fanfiction->title);
    }

    public function test_member_cannot_view_draft_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertNotFound();
    }

    public function test_vorstand_can_view_draft_fanfiction(): void
    {
        $vorstand = User::factory()->create();
        $vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
        $vorstand->switchTeam($this->memberTeam);

        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $vorstand->id,
        ]);

        $response = $this->actingAs($vorstand)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
    }

    public function test_fanfiction_shows_comment_count(): void
    {
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);

        FanfictionComment::factory()->count(3)->create([
            'fanfiction_id' => $fanfiction->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.index'));

        $response->assertOk();
        $response->assertSee('3');
    }

    // ── Kauf (Purchase) ────────────────────────────────────────

    public function test_member_can_purchase_fanfiction_with_reward(): void
    {
        $this->member->incrementTeamPoints(20);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $fanfiction));

        $response->assertRedirect(route('fanfiction.show', $fanfiction));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
        ]);
    }

    public function test_author_can_view_own_rewarded_fanfiction_without_purchase(): void
    {
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Eigene verriegelte Geschichte',
        ]);
        $this->createRewardForFanfiction($fanfiction, 25);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        $response->assertSee('Eigener Beitrag');
        $response->assertDontSee('Diese Geschichte ist gesperrt');
    }

    public function test_show_reloads_wallet_state_after_auto_refund_for_own_fanfiction(): void
    {
        $this->member->incrementTeamPoints(10);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Eigene Geschichte mit Erstattung in Detailansicht',
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction, 5);
        $purchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        $response->assertSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');
        $response->assertViewHas('autoRefundedPurchases', 1);
        $response->assertViewHas('availableBaxx', 10);

        $purchase->refresh();
        $this->assertNotNull($purchase->refunded_at);
        $this->assertNull($purchase->refunded_by);
    }

    public function test_creator_without_linked_author_must_still_unlock_rewarded_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->published()->externalAuthor()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
            'title' => 'Nicht technisch verknüpfte Geschichte',
        ]);
        $this->createRewardForFanfiction($fanfiction, 25);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        $response->assertSee('Diese Geschichte ist gesperrt');
        $response->assertDontSee('Eigener Beitrag');
    }

    public function test_showing_foreign_fanfiction_does_not_refund_unrelated_own_purchases(): void
    {
        $ownFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Eigene Geschichte mit Alt-Kauf',
        ]);
        $ownReward = $this->createRewardForFanfiction($ownFanfiction, 5);
        $purchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $ownReward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $otherAuthor = User::factory()->create();
        $foreignFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $otherAuthor->id,
            'created_by' => $otherAuthor->id,
            'title' => 'Fremde Geschichte',
        ]);
        $this->createRewardForFanfiction($foreignFanfiction, 5);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.show', $foreignFanfiction));

        $response->assertOk();
        $response->assertDontSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');

        $purchase->refresh();
        $this->assertNull($purchase->refunded_at);
    }

    public function test_fanfiction_index_refunds_existing_self_purchases_automatically(): void
    {
        $this->member->incrementTeamPoints(40);

        $firstFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Erste eigene Geschichte',
        ]);
        $secondFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Zweite eigene Geschichte',
        ]);

        $firstReward = $this->createRewardForFanfiction($firstFanfiction, 5);
        $secondReward = $this->createRewardForFanfiction($secondFanfiction, 7);

        $firstPurchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $firstReward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);
        $secondPurchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $secondReward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 7,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.index'));

        $response->assertOk();
        $response->assertSee('2 frühere Eigenkäufe deiner Fanfiction wurden automatisch erstattet.');
        $response->assertSee('40 Baxx verfügbar');
        $response->assertSee('Eigener Beitrag');

        $this->assertNotNull($firstPurchase->fresh()->refunded_at);
        $this->assertNotNull($secondPurchase->fresh()->refunded_at);
        $this->assertTrue($firstPurchase->fresh()->updated_at?->equalTo($firstPurchase->fresh()->refunded_at));
        $this->assertTrue($secondPurchase->fresh()->updated_at?->equalTo($secondPurchase->fresh()->refunded_at));
        $this->assertNull($firstPurchase->fresh()->refunded_by);
        $this->assertNull($secondPurchase->fresh()->refunded_by);
    }

    public function test_fanfiction_index_refunds_self_purchase_for_soft_deleted_own_fanfiction(): void
    {
        $this->member->incrementTeamPoints(10);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Gelöschte eigene Geschichte',
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction, 5);

        $purchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $fanfiction->delete();

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.index'));

        $response->assertOk();
        $response->assertSee('Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.');
        $response->assertSee('10 Baxx verfügbar');

        $purchase->refresh();
        $this->assertNotNull($purchase->refunded_at);
        $this->assertNull($purchase->refunded_by);
    }

    public function test_purchase_route_does_not_charge_for_own_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction, 9);

        $purchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 9,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $fanfiction));

        $response->assertRedirect(route('fanfiction.show', $fanfiction));
        $response->assertSessionHas('info', 'Deine eigene Fanfiction ist bereits freigeschaltet. Frühere Eigenkäufe wurden automatisch erstattet.');

        $purchase->refresh();
        $this->assertNotNull($purchase->refunded_at);
        $this->assertNull($purchase->refunded_by);
        $this->assertSame(1, RewardPurchase::where('user_id', $this->member->id)->count());
    }

    public function test_purchasing_foreign_fanfiction_does_not_refund_unrelated_own_purchases(): void
    {
        $this->member->incrementTeamPoints(20);

        $ownFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $this->member->id,
            'created_by' => $this->member->id,
            'title' => 'Eigene Geschichte mit Alt-Kauf',
        ]);
        $ownReward = $this->createRewardForFanfiction($ownFanfiction, 5);
        $ownPurchase = RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $ownReward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $otherAuthor = User::factory()->create();
        $foreignFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'user_id' => $otherAuthor->id,
            'created_by' => $otherAuthor->id,
            'title' => 'Fremde Kaufgeschichte',
        ]);
        $foreignReward = $this->createRewardForFanfiction($foreignFanfiction, 5);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $foreignFanfiction));

        $response->assertRedirect(route('fanfiction.show', $foreignFanfiction));
        $response->assertSessionHas('success');

        $ownPurchase->refresh();
        $this->assertNull($ownPurchase->refunded_at);
        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $this->member->id,
            'reward_id' => $foreignReward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => 5,
        ]);
    }

    public function test_fanfiction_index_shows_wallet_warning_for_ambiguous_legacy_purchase(): void
    {
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'fanfiction-legacy-warning']);

        RewardPurchase::factory()->create([
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => null,
            'cost_baxx' => 5,
        ]);

        $response = $this->actingAs($this->member)
            ->get(route('fanfiction.index'));

        $response->assertOk();
        $response->assertSee('Baxx-Guthaben wird geprüft');
    }

    public function test_purchase_with_insufficient_baxx_fails(): void
    {
        // Member hat 0 Baxx
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction, 50);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $fanfiction));

        $response->assertRedirect(route('fanfiction.show', $fanfiction));
        $response->assertSessionHasErrors(['reward']);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_double_purchase_is_rejected(): void
    {
        $this->member->incrementTeamPoints(100);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);
        $reward = $this->createRewardForFanfiction($fanfiction);

        // Erster Kauf
        RewardPurchase::create([
            'user_id' => $this->member->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $this->memberTeam->id,
            'cost_baxx' => $reward->cost_baxx,
            'purchased_at' => now(),
        ]);

        // Zweiter Kauf wird abgelehnt
        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $fanfiction));

        $response->assertRedirect(route('fanfiction.show', $fanfiction));
        $response->assertSessionHasErrors(['reward']);
    }

    public function test_purchase_unpublished_fanfiction_returns_404(): void
    {
        $this->member->incrementTeamPoints(20);

        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);
        $this->createRewardForFanfiction($fanfiction);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.purchase', $fanfiction));

        $response->assertNotFound();
    }

    // ── Hilfsmethoden ─────────────────────────────────────────

    private function createRewardForFanfiction(Fanfiction $fanfiction, int $costBaxx = 5): Reward
    {
        $reward = Reward::create([
            'title' => $fanfiction->title,
            'slug' => 'fanfiction-'.Str::slug($fanfiction->title).'-'.$fanfiction->id,
            'description' => Str::limit($fanfiction->content ?? '', 200),
            'category' => 'Fanfiction',
            'cost_baxx' => $costBaxx,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $fanfiction->update(['reward_id' => $reward->id]);

        return $reward;
    }
}

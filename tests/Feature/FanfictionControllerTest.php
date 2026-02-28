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
            'cost_baxx' => 5,
        ]);
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
            'slug' => 'fanfiction-' . Str::slug($fanfiction->title) . '-' . $fanfiction->id,
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

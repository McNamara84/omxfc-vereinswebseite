<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FanfictionCommentControllerTestFixtures::class);

beforeEach(function (): void {
    $this->memberTeam = Team::membersTeam();
    $this->member = User::factory()->create();
    $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $this->member->switchTeam($this->memberTeam);
    $this->otherMember = User::factory()->create();
    $this->otherMember->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $this->otherMember->switchTeam($this->memberTeam);
    $this->fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->member->id]);
});

test('member can create comment', function (): void {
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Das ist ein toller Kommentar!']);
    $response->assertRedirect();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'content' => 'Das ist ein toller Kommentar!']);
});

test('member can reply to comment', function (): void {
    $parentComment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->otherMember->id]);
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Das ist eine Antwort!', 'parent_id' => $parentComment->id]);
    $response->assertRedirect();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'parent_id' => $parentComment->id, 'content' => 'Das ist eine Antwort!']);
});

test('member can edit own comment', function (): void {
    $comment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'content' => 'Original content']);
    $response = $this->actingAs($this->member)->put(route('fanfiction.comments.update', $comment), ['content' => 'Updated content']);
    $response->assertRedirect();
    $comment->refresh();
    expect($comment->content)->toEqual('Updated content');
});

test('member cannot edit others comment', function (): void {
    $comment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->otherMember->id]);
    $response = $this->actingAs($this->member)->put(route('fanfiction.comments.update', $comment), ['content' => 'Hacked content']);
    $response->assertForbidden();
});

test('member can delete own comment', function (): void {
    $comment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id]);
    $response = $this->actingAs($this->member)->delete(route('fanfiction.comments.destroy', $comment));
    $response->assertRedirect();
    $this->assertSoftDeleted('fanfiction_comments', ['id' => $comment->id]);
});

test('member cannot delete others comment', function (): void {
    $comment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->otherMember->id]);
    $response = $this->actingAs($this->member)->delete(route('fanfiction.comments.destroy', $comment));
    $response->assertForbidden();
});

test('vorstand can delete any comment', function (): void {
    $vorstand = User::factory()->create();
    $vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
    $vorstand->switchTeam($this->memberTeam);
    $comment = FanfictionComment::factory()->create(['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id]);
    $response = $this->actingAs($vorstand)->delete(route('fanfiction.comments.destroy', $comment));
    $response->assertRedirect();
    $this->assertSoftDeleted('fanfiction_comments', ['id' => $comment->id]);
});

test('comment requires content', function (): void {
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => '']);
    $response->assertSessionHasErrors('content');
});

test('anwaerter cannot comment', function (): void {
    $anwaerter = User::factory()->create();
    $anwaerter->teams()->attach($this->memberTeam, ['role' => Role::Anwaerter->value]);
    $anwaerter->switchTeam($this->memberTeam);
    $response = $this->actingAs($anwaerter)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Test Kommentar']);
    // Anwärter werden zur Freischaltungsseite redirected
    $response->assertRedirect();
});

test('member cannot comment on locked fanfiction', function (): void {
    $reward = $this->createRewardForFanfiction($this->fanfiction);
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Versuch ohne Freischaltung']);
    $response->assertRedirect(route('fanfiction.show', $this->fanfiction));
    $response->assertSessionHasErrors('reward');
    $this->assertDatabaseMissing('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'content' => 'Versuch ohne Freischaltung']);
});

test('member can comment after purchasing fanfiction', function (): void {
    $reward = $this->createRewardForFanfiction($this->fanfiction);
    RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $reward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Kommentar nach Freischaltung']);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'content' => 'Kommentar nach Freischaltung']);
});

test('author can comment on own locked fanfiction without purchase', function (): void {
    $this->fanfiction->update(['user_id' => $this->member->id]);
    $this->createRewardForFanfiction($this->fanfiction);
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Kommentar zur eigenen Geschichte']);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $this->member->id, 'content' => 'Kommentar zur eigenen Geschichte']);
});

test('commenting on foreign fanfiction does not refund unrelated own purchases', function (): void {
    $ownFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->member->id, 'created_by' => $this->member->id, 'title' => 'Eigene Geschichte mit Alt-Kauf']);
    $ownReward = $this->createRewardForFanfiction($ownFanfiction);
    $ownPurchase = RewardPurchase::create(['user_id' => $this->member->id, 'reward_id' => $ownReward->id, 'wallet_team_id' => $this->memberTeam->id, 'cost_baxx' => $ownReward->cost_baxx, 'purchased_at' => now()]);
    $foreignFanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'user_id' => $this->otherMember->id, 'created_by' => $this->otherMember->id]);
    $response = $this->actingAs($this->member)->post(route('fanfiction.comments.store', $foreignFanfiction), ['content' => 'Kommentar ohne Nebenwirkung']);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $ownPurchase->refresh();
    expect($ownPurchase->refunded_at)->toBeNull();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $foreignFanfiction->id, 'user_id' => $this->member->id, 'content' => 'Kommentar ohne Nebenwirkung']);
});

test('vorstand can comment on locked fanfiction without purchase', function (): void {
    $vorstand = User::factory()->create();
    $vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
    $vorstand->switchTeam($this->memberTeam);
    $this->createRewardForFanfiction($this->fanfiction);
    $response = $this->actingAs($vorstand)->post(route('fanfiction.comments.store', $this->fanfiction), ['content' => 'Vorstand-Kommentar ohne Kauf']);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('fanfiction_comments', ['fanfiction_id' => $this->fanfiction->id, 'user_id' => $vorstand->id, 'content' => 'Vorstand-Kommentar ohne Kauf']);
});

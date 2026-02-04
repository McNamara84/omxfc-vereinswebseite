<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FanfictionCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $member;

    private User $otherMember;

    private Fanfiction $fanfiction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberTeam = Team::membersTeam();

        $this->member = User::factory()->create();
        $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $this->member->switchTeam($this->memberTeam);

        $this->otherMember = User::factory()->create();
        $this->otherMember->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $this->otherMember->switchTeam($this->memberTeam);

        $this->fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->member->id,
        ]);
    }

    public function test_member_can_create_comment(): void
    {
        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.comments.store', $this->fanfiction), [
                'content' => 'Das ist ein toller Kommentar!',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('fanfiction_comments', [
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->member->id,
            'content' => 'Das ist ein toller Kommentar!',
        ]);
    }

    public function test_member_can_reply_to_comment(): void
    {
        $parentComment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->otherMember->id,
        ]);

        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.comments.store', $this->fanfiction), [
                'content' => 'Das ist eine Antwort!',
                'parent_id' => $parentComment->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('fanfiction_comments', [
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->member->id,
            'parent_id' => $parentComment->id,
            'content' => 'Das ist eine Antwort!',
        ]);
    }

    public function test_member_can_edit_own_comment(): void
    {
        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->member->id,
            'content' => 'Original content',
        ]);

        $response = $this->actingAs($this->member)
            ->put(route('fanfiction.comments.update', $comment), [
                'content' => 'Updated content',
            ]);

        $response->assertRedirect();

        $comment->refresh();
        $this->assertEquals('Updated content', $comment->content);
    }

    public function test_member_cannot_edit_others_comment(): void
    {
        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->otherMember->id,
        ]);

        $response = $this->actingAs($this->member)
            ->put(route('fanfiction.comments.update', $comment), [
                'content' => 'Hacked content',
            ]);

        $response->assertForbidden();
    }

    public function test_member_can_delete_own_comment(): void
    {
        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)
            ->delete(route('fanfiction.comments.destroy', $comment));

        $response->assertRedirect();
        $this->assertSoftDeleted('fanfiction_comments', ['id' => $comment->id]);
    }

    public function test_member_cannot_delete_others_comment(): void
    {
        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->otherMember->id,
        ]);

        $response = $this->actingAs($this->member)
            ->delete(route('fanfiction.comments.destroy', $comment));

        $response->assertForbidden();
    }

    public function test_vorstand_can_delete_any_comment(): void
    {
        $vorstand = User::factory()->create();
        $vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
        $vorstand->switchTeam($this->memberTeam);

        $comment = FanfictionComment::factory()->create([
            'fanfiction_id' => $this->fanfiction->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($vorstand)
            ->delete(route('fanfiction.comments.destroy', $comment));

        $response->assertRedirect();
        $this->assertSoftDeleted('fanfiction_comments', ['id' => $comment->id]);
    }

    public function test_comment_requires_content(): void
    {
        $response = $this->actingAs($this->member)
            ->post(route('fanfiction.comments.store', $this->fanfiction), [
                'content' => '',
            ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_anwaerter_cannot_comment(): void
    {
        $anwaerter = User::factory()->create();
        $anwaerter->teams()->attach($this->memberTeam, ['role' => Role::Anwaerter->value]);
        $anwaerter->switchTeam($this->memberTeam);

        $response = $this->actingAs($anwaerter)
            ->post(route('fanfiction.comments.store', $this->fanfiction), [
                'content' => 'Test Kommentar',
            ]);

        // Anwärter werden zur Freischaltungsseite redirected
        $response->assertRedirect();
    }
}

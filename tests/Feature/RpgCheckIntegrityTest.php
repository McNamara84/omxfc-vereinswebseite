<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Services\RpgCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

class RpgCheckIntegrityTest extends TestCase
{
    use RefreshDatabase, RpgCheckFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkFixtures();
    }

    public function test_opposed_checks_wait_for_both_and_compare_totals(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([6, 6], [1, 1]);
        $first = $this->rollCheck($check);
        $this->assertSame('pending', $first->status);
        $this->assertNull($first->winner_position);
        $this->assertNull($first->participants[0]->result_kind);
        $result = $this->rollCheck($check, $this->otherPlayer, 2);
        $this->assertSame('completed', $result->status);
        $this->assertSame(1, $result->winner_position);
        $this->assertSame(10, $result->comparison_margin);
        $this->assertSame('critical_success', $result->participants[0]->result_kind);
        $this->assertSame('fumble', $result->participants[1]->result_kind);
        $this->expectException(ValidationException::class);
        app(RpgCheckService::class)->cancel($this->leader, $check->id, 'Zu spät');
    }

    public function test_tie_is_decided_exactly_once_and_remains_documented(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([4, 3], [2, 5]);
        $this->rollCheck($check);
        $this->assertSame('awaiting_decision', $this->rollCheck($check, $this->otherPlayer, 2)->status);
        $url = route('rpg.checks.resolve', $check);
        $this->actingAs($this->player)->postJson($url, ['resolution' => 'side_2', 'reason' => 'Beide greifen zu'])->assertForbidden();
        $this->actingAs($this->leader)->postJson($url, ['resolution' => 'side_2', 'reason' => 'Birka hat den besseren Stand'])->assertOk()->assertJsonPath('winner_position', 2);
        $this->postJson($url, ['resolution' => 'side_2', 'reason' => 'Birka hat den besseren Stand'])->assertOk();
        $this->postJson($url, ['resolution' => 'side_1', 'reason' => 'Anders'])->assertUnprocessable();
        $this->assertNull($check->fresh()->participants[0]->result_kind);
    }

    public function test_partial_comparison_can_be_cancelled_without_erasing_dice(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([2, 5]);
        $this->rollCheck($check);
        $this->actingAs($this->leader)->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Situation beendet'])->assertOk();
        $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Situation beendet'])->assertOk();
        $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Anders'])->assertUnprocessable();
        $this->assertSame(9, $check->fresh()->participants[0]->total);
        $this->expectException(ValidationException::class);
        $this->rollCheck($check, $this->otherPlayer, 2);
    }

    public function test_departed_opponent_and_new_leader_participant_cannot_roll(): void
    {
        $check = $this->createCheck('opposed');
        $this->rpgTeam->users()->detach($this->otherPlayer);
        $this->actingAs($this->player)->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertUnprocessable();
        $this->rpgTeam->users()->attach($this->otherPlayer, ['role' => 'Mitglied']);
        $this->rpgTeam->update(['user_id' => $this->otherPlayer->id]);
        $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertUnprocessable();
    }

    public function test_character_deletion_cancels_whole_comparison_and_preserves_history(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([4, 3]);
        $this->rollCheck($check);
        $this->actingAs($this->player)->delete(route('rpg.characters.destroy', $this->character))->assertRedirect();
        $check->refresh();
        $this->assertSame('cancelled', $check->status);
        $this->assertNull($check->participants[0]->rpg_character_id);
        $this->assertSame(9, $check->participants[0]->total);
        $this->getJson(route('rpg.checks.show', $check))->assertOk()->assertJsonPath('participants.0.character_name', 'Arkon');
    }

    public function test_user_cascade_deletion_cancels_comparisons(): void
    {
        $check = $this->createCheck('opposed');
        app(DeleteUser::class)->delete($this->player);
        $this->assertSame('cancelled', $check->fresh()->status);
        $this->assertNull($check->fresh()->participants[0]->owner_id);
    }

    public function test_team_deletion_does_not_grant_new_team_access_to_history(): void
    {
        $check = $this->createCheck();
        app(DeleteTeam::class)->delete($this->rpgTeam);
        $this->assertSame('cancelled', $check->fresh()->status);
        $this->assertNull($check->fresh()->batch->team_id);
    }

    public function test_deletion_hooks_allow_rolling_back_the_feature_migration(): void
    {
        $migration = require base_path('database/migrations/2026_09_26_140000_create_rpg_checks.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('rpg_checks'));
        $this->character->delete();
        $this->player->delete();
        $migration->up();
        $this->assertDatabaseCount('rpg_checks', 0);
    }
}

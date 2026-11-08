<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Services\RpgCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCheckFixtures::class);

beforeEach(function (): void {
    $this->checkFixtures();
});

test('opposed checks wait for both and compare totals', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([6, 6], [1, 1]);
    $first = $this->rollCheck($check);
    expect($first->status)->toBe('pending')
        ->and($first->winner_position)->toBeNull()
        ->and($first->participants[0]->result_kind)->toBeNull();
    $result = $this->rollCheck($check, $this->otherPlayer, 2);
    expect($result->status)->toBe('completed')
        ->and($result->winner_position)->toBe(1)
        ->and($result->comparison_margin)->toBe(10)
        ->and($result->participants[0]->result_kind)->toBe('critical_success')
        ->and($result->participants[1]->result_kind)->toBe('fumble')
        ->and(fn () => app(RpgCheckService::class)->cancel($this->leader, $check->id, 'Zu spät'))->toThrow(ValidationException::class);
});

test('tie is decided exactly once and remains documented', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([4, 3], [2, 5]);
    $this->rollCheck($check);
    expect($this->rollCheck($check, $this->otherPlayer, 2)->status)->toBe('awaiting_decision');
    $url = route('rpg.checks.resolve', $check);
    $this->actingAs($this->player)->postJson($url, ['resolution' => 'side_2', 'reason' => 'Beide greifen zu'])->assertForbidden();
    $this->actingAs($this->leader)->postJson($url, ['resolution' => 'side_2', 'reason' => 'Birka hat den besseren Stand'])->assertOk()->assertJsonPath('winner_position', 2);
    $this->postJson($url, ['resolution' => 'side_2', 'reason' => 'Birka hat den besseren Stand'])->assertOk();
    $this->postJson($url, ['resolution' => 'side_1', 'reason' => 'Anders'])->assertUnprocessable();
    expect($check->fresh()->participants[0]->result_kind)->toBeNull();
});

test('partial comparison can be cancelled without erasing dice', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([2, 5]);
    $this->rollCheck($check);
    $this->actingAs($this->leader)->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Situation beendet'])->assertOk();
    $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Situation beendet'])->assertOk();
    $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Anders'])->assertUnprocessable();
    expect($check->fresh()->participants[0]->total)->toBe(9)
        ->and(fn () => $this->rollCheck($check, $this->otherPlayer, 2))->toThrow(ValidationException::class);
});

test('departed opponent and new leader participant cannot roll', function (): void {
    $check = $this->createCheck('opposed');
    $this->rpgTeam->users()->detach($this->otherPlayer);
    $this->actingAs($this->player)->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertUnprocessable();
    $this->rpgTeam->users()->attach($this->otherPlayer, ['role' => 'Mitglied']);
    $this->rpgTeam->update(['user_id' => $this->otherPlayer->id]);
    $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertUnprocessable();
});

test('character deletion cancels whole comparison and preserves history', function (): void {
    $check = $this->createCheck('opposed');
    $this->dice([4, 3]);
    $this->rollCheck($check);
    $this->actingAs($this->player)->delete(route('rpg.characters.destroy', $this->character))->assertRedirect();
    $check->refresh();
    expect($check->status)->toBe('cancelled')
        ->and($check->participants[0]->rpg_character_id)->toBeNull()
        ->and($check->participants[0]->total)->toBe(9);
    $this->getJson(route('rpg.checks.show', $check))->assertOk()->assertJsonPath('participants.0.character_name', 'Arkon');
});

test('user cascade deletion cancels comparisons', function (): void {
    $check = $this->createCheck('opposed');
    app(DeleteUser::class)->delete($this->player);
    expect($check->fresh()->status)->toBe('cancelled')
        ->and($check->fresh()->participants[0]->owner_id)->toBeNull();
});

test('team deletion does not grant new team access to history', function (): void {
    $check = $this->createCheck();
    app(DeleteTeam::class)->delete($this->rpgTeam);
    expect($check->fresh()->status)->toBe('cancelled')
        ->and($check->fresh()->batch->team_id)->toBeNull();
});

test('deletion hooks allow rolling back the feature migration', function (): void {
    $migration = require base_path('database/migrations/2026_09_26_140000_create_rpg_checks.php');
    $migration->down();
    expect(Schema::hasTable('rpg_checks'))->toBeFalse();
    $this->character->delete();
    $this->player->delete();
    $migration->up();
    $this->assertDatabaseCount('rpg_checks', 0);
});

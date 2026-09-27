<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Services\RpgAccess;
use App\Services\RpgCheckDice;
use App\Services\RpgCheckQuery;
use App\Services\RpgCheckService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

class RpgCheckMembershipTest extends TestCase
{
    use RefreshDatabase, RpgCheckFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkFixtures();
    }

    public function test_applicant_cannot_access_checks_via_another_current_team(): void
    {
        $pending = $this->createCheck();
        $completed = $this->createCheck();
        $this->dice([3, 4]);
        $this->rollCheck($completed);
        $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Anwaerter->value]);
        $this->assertNotSame($this->rpgTeam->id, $this->player->current_team_id);

        $this->actingAs($this->player)->getJson(route('rpg.checks.index'))->assertForbidden();
        $this->get(route('rpg.checks.index'))->assertForbidden();
        $this->getJson(route('rpg.checks.index', ['tab' => 'history']))->assertForbidden();
        $this->getJson(route('rpg.checks.hints'))->assertForbidden();
        foreach ([$pending, $completed] as $check) {
            $this->get(route('rpg.checks.show', $check))->assertForbidden();
            $this->getJson(route('rpg.checks.show', $check))->assertForbidden();
            $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertForbidden();
        }
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Persönliche Rollenspiel-Proben');
        $this->assertFalse(Gate::forUser($this->player)->allows('access-rpg-checks'));
        $this->assertFalse(Gate::forUser($this->player)->allows('view', $completed));
        $this->assertNull($pending->participants[0]->fresh()->rolled_at);
    }

    #[DataProvider('directAccessPaths')]
    public function test_direct_query_and_roll_calls_also_reject_applicants(string $path): void
    {
        $check = $this->createCheck();
        $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Anwaerter->value]);
        $this->mock(RpgCheckDice::class)->shouldNotReceive('roll');
        try {
            if ($path === 'query') {
                app(RpgCheckQuery::class)->detail($this->player, $check);
            } else {
                app(RpgCheckService::class)->roll($this->player, $check->id, $check->participants[0]->id);
            }
            $this->fail('An applicant must not bypass authorization by calling the service directly.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNull($check->participants[0]->fresh()->rolled_at);
    }

    public static function directAccessPaths(): array
    {
        return [['query'], ['roll']];
    }

    #[DataProvider('requestKinds')]
    public function test_applicant_characters_are_unselectable_and_crafted_requests_are_atomic(string $kind): void
    {
        $input = $this->checkInput($kind === 'fixed' ? 'fixed' : 'opposed');
        if ($kind === 'group') {
            $input['mode'] = 'fixed';
            $input['difficulty'] = 10;
        }
        $applicant = $kind === 'fixed' ? $this->player : $this->otherPlayer;
        $character = $kind === 'fixed' ? $this->character : $this->otherCharacter;
        $this->actingAs($this->leader)->postJson(route('rpg.checks.preview'), $input)->assertOk();
        $this->rpgTeam->users()->updateExistingPivot($applicant->id, ['role' => Role::Anwaerter->value]);

        $this->get(route('rpg.checks.create'))->assertOk()->assertViewHas('config',
            fn ($config) => ! in_array($character->id, array_column($config['characters'], 'id'), true));
        $this->get(route('rpg.checks.index'))->assertOk()->assertViewHas('characters',
            fn ($characters) => ! in_array($character->id, array_column($characters, 'id'), true));
        $this->postJson(route('rpg.checks.preview'), $input)->assertUnprocessable();
        // Even a previously valid preview must not authorize an inactive participant.
        $this->postJson(route('rpg.checks.store'), $input)->assertUnprocessable();
        $this->assertDatabaseCount('rpg_check_batches', 0);
        $this->assertDatabaseCount('rpg_checks', 0);
        $this->assertDatabaseCount('rpg_check_participants', 0);
    }

    public static function requestKinds(): array
    {
        return [['fixed'], ['opposed'], ['group']];
    }

    public function test_pending_comparison_with_new_applicant_is_blocked_in_all_views_and_actions(): void
    {
        $check = $this->createCheck('opposed');
        $this->rpgTeam->users()->updateExistingPivot($this->otherPlayer->id, ['role' => Role::Anwaerter->value]);
        $this->mock(RpgCheckDice::class)->shouldNotReceive('roll');
        $this->actingAs($this->player)->getJson(route('rpg.checks.show', $check))->assertOk()->assertJsonPath('participants.0.can_roll', false);
        foreach (['index', 'hints'] as $route) {
            $this->getJson(route('rpg.checks.'.$route))->assertOk()->assertJsonPath('checks.0.participants.0.can_roll', false);
        }
        $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertUnprocessable();
        $this->actingAs($this->otherPlayer)->postJson(route('rpg.checks.roll', [$check, $check->participants[1]]))->assertForbidden();

        $this->actingAs($this->leader)->getJson(route('rpg.checks.show', $check))->assertOk()->assertJsonPath('executable', false)->assertJsonPath('can_cancel', true);
        foreach (['index', 'hints'] as $route) {
            $this->getJson(route('rpg.checks.'.$route))->assertOk()->assertJsonPath('checks.0.executable', false);
        }
        $this->assertNull($check->participants[0]->fresh()->rolled_at);
        $this->assertNull($check->participants[1]->fresh()->rolled_at);
        $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Teilnehmer ist nicht mehr aktives Mitglied'])->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_tie_with_new_applicant_cannot_be_resolved_but_can_be_cancelled(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([3, 4], [4, 3]);
        $this->rollCheck($check);
        $this->rollCheck($check, $this->otherPlayer, 2);
        $this->rpgTeam->users()->updateExistingPivot($this->otherPlayer->id, ['role' => Role::Anwaerter->value]);
        $this->actingAs($this->leader)->getJson(route('rpg.checks.show', $check))->assertOk()->assertJsonPath('can_resolve', false);
        $this->getJson(route('rpg.checks.index'))->assertOk()->assertJsonPath('checks.0.can_resolve', false);
        $this->postJson(route('rpg.checks.resolve', $check), ['resolution' => 'side_1', 'reason' => 'Entscheidung'])->assertUnprocessable();
        $this->assertSame('awaiting_decision', $check->fresh()->status);
        $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Teilnehmer ist nicht mehr aktives Mitglied'])->assertOk();
        $this->assertSame(9, $check->participants[0]->fresh()->total);
        $this->assertSame(9, $check->participants[1]->fresh()->total);
    }

    public function test_ag_owner_with_applicant_role_cannot_lead_checks(): void
    {
        $check = $this->createCheck();
        $this->rpgTeam->users()->updateExistingPivot($this->leader->id, ['role' => Role::Anwaerter->value]);
        $this->assertFalse(app(RpgAccess::class)->isLeader($this->leader));
        $this->assertFalse(Gate::forUser($this->leader)->allows('manage-rpg-checks'));
        $this->actingAs($this->leader)->get(route('rpg.checks.create'))->assertForbidden();
        $this->postJson(route('rpg.checks.preview'), $this->checkInput())->assertForbidden();
        $this->postJson(route('rpg.checks.store'), $this->checkInput())->assertForbidden();
        $this->postJson(route('rpg.checks.cancel', $check), ['reason' => 'Kein aktives Mitglied'])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(RpgCheckService::class)->preview($this->leader, $this->checkInput());
    }

    public function test_active_membership_is_scoped_to_the_ag_and_rechecked_after_admission(): void
    {
        $check = $this->createCheck();
        $otherTeam = Team::factory()->create(['name' => 'Andere AG', 'personal_team' => false]);
        $otherTeam->users()->attach($this->player, ['role' => Role::Anwaerter->value]);
        $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Anwaerter->value]);
        $this->actingAs($this->player)->getJson(route('rpg.checks.hints'))->assertForbidden();

        $this->rpgTeam->users()->updateExistingPivot($this->player->id, ['role' => Role::Mitglied->value]);
        $this->getJson(route('rpg.checks.hints'))->assertOk()->assertJsonPath('checks.0.participants.0.can_roll', true);
        $this->actingAs($this->leader)->get(route('rpg.checks.create'))->assertOk()->assertViewHas('config',
            fn ($config) => in_array($this->character->id, array_column($config['characters'], 'id'), true));
        $this->postJson(route('rpg.checks.preview'), $this->checkInput())->assertOk();
        $this->dice([3, 4]);
        $this->actingAs($this->player)->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertOk()->assertJsonPath('participants.0.total', 9);
    }
}

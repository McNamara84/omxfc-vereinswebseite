<?php

namespace Tests\Feature;

use App\Services\RpgCheckQuery;
use App\Services\RpgCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgCheckFixtures;
use Tests\TestCase;

class RpgCheckPrivacyTest extends TestCase
{
    use RefreshDatabase, RpgCheckFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkFixtures();
    }

    private function assertSecret(array $data): void
    {
        $forbidden = ['difficulty', 'modifiers', 'modifier_total', 'base_total', 'attribute_value', 'skill_value',
            'dice', 'die_one', 'die_two', 'total', 'raw_total', 'margin', 'result_kind', 'winner_position',
            'comparison_margin', 'resolution_reason', 'resolved_at', 'completed_at', 'executable'];
        foreach ($data as $key => $value) {
            $this->assertNotContains($key, $forbidden);
            if (is_array($value)) {
                $this->assertSecret($value);
            }
        }
    }

    public function test_hidden_result_is_absent_from_every_player_response(): void
    {
        $input = $this->checkInput(visibility: 'hidden');
        $input['difficulty'] = 743;
        $input['participants'][0]['modifiers'] = [['value' => 913, 'description' => 'SECRET-SITUATION']];
        $check = $this->createCheck(input: $input);
        $this->dice([6, 6]);
        $this->actingAs($this->player);
        $this->assertSecret($this->getJson(route('rpg.checks.show', $check))->assertOk()->json());
        $this->get(route('rpg.checks.show', $check))->assertOk()->assertDontSee('SECRET-SITUATION')->assertDontSee('743')->assertDontSee('913');
        $response = $this->postJson(route('rpg.checks.roll', [$check, $check->participants[0]]))->assertOk();
        $this->assertSecret($response->json());
        $this->assertSecret($this->getJson(route('rpg.checks.index', ['tab' => 'history']))->json('checks.0'));
        $this->get(route('dashboard'))->assertOk()->assertDontSee('SECRET-SITUATION');
        $this->actingAs($this->leader)->getJson(route('rpg.checks.show', $check))->assertJsonPath('participants.0.dice', [6, 6])->assertJsonPath('difficulty', 743);
    }

    public function test_hidden_winner_tie_and_decision_have_identical_player_projection(): void
    {
        $check = $this->createCheck('opposed', 'hidden');
        $this->dice([3, 4], [4, 3]);
        $this->rollCheck($check);
        $query = app(RpgCheckQuery::class);
        $before = $query->detail($this->player, $check);
        $this->rollCheck($check, $this->otherPlayer, 2);
        $this->assertSame($before, $query->detail($this->player, $check));
        app(RpgCheckService::class)->resolve($this->leader, $check->id, 'side_2', 'SECRET-DECISION');
        $this->assertSame($before, $query->detail($this->player, $check));
        $this->assertSecret($before);
        $this->assertSame(0, $query->listing($this->player, hints: true)['total']);
    }

    public function test_open_comparison_never_exposes_opponents_numbers(): void
    {
        $check = $this->createCheck('opposed');
        $this->dice([5, 6], [1, 2]);
        $this->rollCheck($check);
        $this->rollCheck($check, $this->otherPlayer, 2);
        $data = app(RpgCheckQuery::class)->detail($this->player, $check);
        $this->assertSame(13, $data['participants'][0]['total']);
        $this->assertSame(['id', 'position', 'character_name'], array_keys($data['participants'][1]));
        $this->assertSame(1, $data['winner_position']);
        $this->assertArrayNotHasKey('comparison_margin', $data);
        $this->assertArrayNotHasKey('margin', $data['participants'][0]);
    }
}

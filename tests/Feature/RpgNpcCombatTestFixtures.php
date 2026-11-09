<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCombat;
use App\Models\RpgNpc;
use App\Services\RpgCombat\CombatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgCombatFixtures;

trait RpgNpcCombatTestFixtures
{
    use RefreshDatabase, RpgCombatFixtures;

    private RpgNpc $npc;

    private function invitation(array $extra = []): RpgCombat
    {
        return app(CombatService::class)->challenge($this->leader, array_replace(['kind' => 'npc_vs_player', 'npc_id' => $this->npc->id, 'npc_revision' => $this->npc->revision, 'opponent_id' => $this->character->id, 'opponent_revision' => $this->character->revision, 'distance' => 1, 'submission_key' => (string) Str::uuid()], $extra));
    }

    private function accepted(): RpgCombat
    {
        return app(CombatService::class)->command($this->player, $this->invitation()->id, 'accept', (string) Str::uuid());
    }

    private function step(RpgCombat $combat, string $type, int $side, array $input = []): RpgCombat
    {
        $decision = $combat->decisions()->where('status', 'pending')->where('type', $type)->where('side', $side)->sole();

        return app(CombatService::class)->decide($side === 2 ? $this->player : $this->leader, $combat->id, $decision->id, $input);
    }

    private function active(): RpgCombat
    {
        $combat = $this->accepted();
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        $this->combatDice->values = [6, 1];
        foreach ([1, 2] as $side) {
            $combat = $this->step($combat, 'initiative', $side);
        }

        return $combat;
    }
}

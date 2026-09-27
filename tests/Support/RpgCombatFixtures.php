<?php

namespace Tests\Support;

use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\User;
use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatService;
use Illuminate\Support\Str;

trait RpgCombatFixtures
{
    use RpgProgressionFixtures;

    protected User $opponent;

    protected RpgCharacter $otherCharacter;

    protected CombatTestDice $combatDice;

    protected function combatFixtures(): void
    {
        $this->progressionFixtures();
        $this->opponent = $this->progressionMember();
        $this->rpgTeam->users()->attach($this->opponent, ['role' => 'mitglied']);
        $this->otherCharacter = RpgCharacter::factory()->create(['user_id' => $this->opponent->id, 'character_name' => 'Mira', 'payload' => $this->progressionPayload()])->refresh();
        $this->combatDice = new CombatTestDice;
        $this->app->instance(CombatDice::class, $this->combatDice);
    }

    protected function combatInput(array $overrides = []): array
    {
        return array_replace(['submission_key' => (string) Str::uuid(), 'character_id' => $this->character->id, 'opponent_id' => $this->otherCharacter->id,
            'revision' => $this->character->revision, 'opponent_revision' => $this->otherCharacter->revision, 'distance' => 1], $overrides);
    }

    protected function combat(bool $accept = true): RpgCombat
    {
        $service = app(CombatService::class);
        $combat = $service->challenge($this->player, $this->combatInput());

        return $accept ? $service->command($this->opponent, $combat->id, 'accept', (string) Str::uuid()) : $combat;
    }

    protected function decision(RpgCombat $combat, string $type, int $side, array $input = []): RpgCombat
    {
        $decision = $combat->decisions()->where('status', 'pending')->where('type', $type)->where('side', $side)->firstOrFail();
        $user = $side === 0 ? $this->leader : ($side === 1 ? $this->player : $this->opponent);

        return app(CombatService::class)->decide($user, $combat->id, $decision->id, $input);
    }

    protected function activeCombat(): RpgCombat
    {
        $combat = $this->combat();
        foreach ([1, 2] as $side) {
            $combat = $this->decision($combat, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        $this->combatDice->values = [6, 1];
        $combat = $this->decision($combat, 'initiative', 1);

        return $this->decision($combat, 'initiative', 2);
    }
}

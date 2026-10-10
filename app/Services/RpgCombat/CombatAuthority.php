<?php

namespace App\Services\RpgCombat;

use App\Models\RpgCombat;
use App\Models\RpgCombatDecision;
use App\Models\User;
use App\Services\RpgAccess;

final class CombatAuthority
{
    public function __construct(private RpgAccess $access) {}

    public function leader(RpgCombat $combat): ?int
    {
        $team = $this->access->team();

        return $team && $team->id === $combat->team_id && $this->access->isMember($team->user_id, $team) ? $team->user_id : null;
    }

    public function controller(RpgCombat $combat, int $side): ?int
    {
        $participant = $combat->participants->firstWhere('side', $side);

        return $participant?->participant_kind === 'npc' ? $this->leader($combat) : $participant?->owner_id;
    }

    public function sides(RpgCombat $combat, User $user): array
    {
        return $combat->participants->filter(fn ($p) => $this->controller($combat, $p->side) === $user->id)->pluck('side')->all();
    }

    public function recipient(RpgCombat $combat, RpgCombatDecision $decision): ?int
    {
        if ($decision->type === 'npc_order') {
            return $this->controller($combat, (int) $decision->controller_side);
        }
        if ($decision->type === 'ruling' || $combat->participants->firstWhere('side', $decision->side)?->participant_kind === 'npc') {
            return $this->leader($combat);
        }

        return $this->controller($combat, (int) $decision->controller_side);
    }

    public function canDecide(RpgCombat $combat, RpgCombatDecision $decision, User $user): bool
    {
        if ($decision->type === 'ruling' && $combat->kind !== 'npc_vs_player' && $combat->participants->contains('owner_id', $user->id)) {
            return false;
        }

        return $this->recipient($combat, $decision) === $user->id;
    }

    public function manual(RpgCombat $combat, RpgCombatDecision $decision): bool
    {
        return $decision->type !== 'npc_order' && $combat->kind === 'npc_vs_player' && ($decision->type === 'ruling'
            || $combat->participants->firstWhere('side', $decision->side)?->participant_kind === 'npc'
            || $combat->participants->firstWhere('side', $decision->controller_side)?->participant_kind === 'npc');
    }
}

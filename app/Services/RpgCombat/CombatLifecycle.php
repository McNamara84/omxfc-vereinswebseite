<?php

namespace App\Services\RpgCombat;

use App\Models\RpgCombat;
use App\Services\RpgAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CombatLifecycle
{
    public function character(int $id): void
    {
        $this->cancel(fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('rpg_character_id', $id)));
    }

    public function user(int $id): void
    {
        $this->membership($id);
        if (Schema::hasTable('rpg_combat_milestones')) {
            foreach (['challenger' => 1, 'defender' => 2] as $prefix => $side) {
                DB::table('rpg_combat_milestones')->whereIn('rpg_combat_id', DB::table('rpg_combat_participants')->where('owner_id', $id)->where('side', $side)->select('rpg_combat_id'))
                    ->update([$prefix.'_name' => 'Ehemaliges Mitglied']);
            }
        }
    }

    public function membership(int $id): void
    {
        $this->cancel(fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('owner_id', $id)));
    }

    public function team(int $id): void
    {
        $this->cancel(fn ($q) => $q->where('team_id', $id));
    }

    private function cancel(callable $scope): void
    {
        if (! Schema::hasTable('rpg_combats')) {
            return;
        }
        DB::transaction(function () use ($scope) {
            app(RpgAccess::class)->team(lock: true);
            $query = RpgCombat::whereIn('status', ['challenged', 'preparing', 'active', 'awaiting_ruling']);
            $scope($query);
            foreach ($query->orderBy('id')->lockForUpdate()->get() as $combat) {
                app(CombatService::class)->close($combat, 'membership_changed');
            }
        });
    }
}

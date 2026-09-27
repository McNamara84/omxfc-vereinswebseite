<?php

namespace App\Services;

use App\Models\RpgCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RpgCheckLifecycle
{
    public function character(int $id): void
    {
        $this->cancel(fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('rpg_character_id', $id)));
    }

    public function user(int $id): void
    {
        $this->cancel(fn ($q) => $q->where(function ($q) use ($id): void {
            $q->whereHas('participants', fn ($p) => $p->where('owner_id', $id))
                ->orWhereHas('batch', fn ($b) => $b->whereHas('team', fn ($t) => $t->where('user_id', $id)));
        }));
    }

    public function team(int $id): void
    {
        $this->cancel(fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('team_id', $id)));
    }

    private function cancel(callable $scope): void
    {
        if (! Schema::hasTable('rpg_checks')) {
            return;
        }
        DB::transaction(function () use ($scope): void {
            // Entry points lock the AG before deleting a character/user/team.
            $query = RpgCheck::whereIn('status', ['pending', 'awaiting_decision']);
            $scope($query);
            foreach ($query->orderBy('id')->lockForUpdate()->get() as $check) {
                $check->update(['status' => 'cancelled', 'cancelled_at' => now(),
                    'cancellation_reason' => 'Ein beteiligter Charakter, ein Benutzer oder die AG wurde gelöscht.']);
            }
        });
    }
}

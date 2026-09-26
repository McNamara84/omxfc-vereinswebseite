<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Services\RpgAccess;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     */
    public function delete(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            app(RpgAccess::class)->team(lock: true);
            $team->purge();
        });
    }
}

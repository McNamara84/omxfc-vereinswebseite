<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Http\Exceptions\HttpResponseException;

class MembersTeamProvider
{
    public function getMembersTeamOrAbort(): Team
    {
        $team = Team::membersTeam();

        if (! $team) {
            throw new HttpResponseException(
                redirect('/')->with('error', 'Team "Mitglieder" nicht gefunden.')
            );
        }

        return $team;
    }
}

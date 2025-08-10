<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class MitgliederKarteMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_role_is_accessible(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'Mitglied']);

        $members = $team->users()
            ->as('pivot')
            ->wherePivotNotIn('role', ['Anwärter'])
            ->select('users.id', 'users.name', 'users.plz', 'users.land', 'users.stadt', 'team_user.role as pivot_role')
            ->get();

        $member = $members->first();

        $this->assertSame('Mitglied', $member->pivot->role);
    }
}


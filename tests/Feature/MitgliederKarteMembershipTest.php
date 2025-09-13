<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Enums\Role;

class MitgliederKarteMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_role_is_accessible(): void
    {
        $team = Team::factory()->create();
        $team->users()->detach();

        $member = User::factory()->create();
        $applicant = User::factory()->create();

        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        $members = $team->activeUsers()
            ->as('pivot')
            ->select('users.id', 'users.name', 'users.plz', 'users.land', 'users.stadt')
            ->withPivot('role')
            ->get();

        $this->assertCount(1, $members);
        $retrieved = $members->first();

        $this->assertSame($member->id, $retrieved->id);
        $this->assertSame(Role::Mitglied->value, $retrieved->pivot->role);
    }
}


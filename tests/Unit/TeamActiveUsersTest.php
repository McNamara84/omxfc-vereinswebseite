<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\Role;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Team::class)]
class TeamActiveUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_users_excludes_applicants(): void
    {
        $team = Team::factory()->create();
        $team->users()->detach();

        $member = User::factory()->create();
        $applicant = User::factory()->create();

        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        $active = $team->activeUsers()->get();

        $this->assertTrue($active->contains($member));
        $this->assertFalse($active->contains($applicant));
    }
}

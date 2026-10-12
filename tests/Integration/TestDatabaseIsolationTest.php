<?php

namespace Tests\Integration;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_fixtures_can_be_modified_inside_the_test_transaction(): void
    {
        $admin = User::where('email', 'info@maddraxikon.com')->sole();
        $admin->update(['name' => 'Changed inside this transaction']);
        Team::membersTeam()->update(['description' => 'Changed inside this transaction']);

        $this->assertSame('Changed inside this transaction', $admin->fresh()->name);
    }

    #[Depends('test_standard_fixtures_can_be_modified_inside_the_test_transaction')]
    public function test_standard_fixtures_have_no_changes_from_previous_tests(): void
    {
        $this->assertSame('Holger Ehrmann', User::where('email', 'info@maddraxikon.com')->sole()->name);
        $this->assertNotSame('Changed inside this transaction', Team::membersTeam()->description);
        $this->assertDatabaseCount('users', 2);
    }
}

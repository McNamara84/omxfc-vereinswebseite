<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(User::class)]
class UserPublicFirstNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_first_name_prefers_vorname(): void
    {
        $user = User::make([
            'name' => 'Martin Gobrecht',
            'vorname' => 'Martin',
        ]);

        $this->assertSame('Martin', $user->publicFirstName());
    }

    public function test_public_first_name_falls_back_to_first_name_token(): void
    {
        $user = User::make([
            'name' => 'Leitung Test',
            'vorname' => null,
        ]);

        $this->assertSame('Leitung', $user->publicFirstName());
    }

    public function test_public_first_name_returns_null_when_no_name_is_available(): void
    {
        $user = User::make([
            'name' => '   ',
            'vorname' => null,
        ]);

        $this->assertNull($user->publicFirstName());
    }
}
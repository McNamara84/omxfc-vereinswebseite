<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
class UserNicknameOrNameTest extends TestCase
{
    public function test_it_prefers_a_trimmed_nickname(): void
    {
        $user = new User([
            'name' => 'Max Mustermann',
            'alias' => '  Maxi  ',
        ]);

        $this->assertSame('Maxi', $user->nicknameOrName());
    }

    public function test_it_falls_back_to_the_name_without_a_nickname(): void
    {
        $user = new User([
            'name' => 'Max Mustermann',
            'alias' => null,
        ]);

        $this->assertSame('Max Mustermann', $user->nicknameOrName());
    }

    public function test_it_falls_back_to_the_name_for_a_blank_nickname(): void
    {
        $user = new User([
            'name' => '  Max Mustermann  ',
            'alias' => '   ',
        ]);

        $this->assertSame('Max Mustermann', $user->nicknameOrName());
    }
}

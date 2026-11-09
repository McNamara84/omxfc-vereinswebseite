<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\UsesCommittedMembershipDatabase;
use Tests\TestCase;

abstract class MembershipContextTestFixtures extends TestCase
{
    use CreatesUserWithRole;
    use UsesCommittedMembershipDatabase;
}

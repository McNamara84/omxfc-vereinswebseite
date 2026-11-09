<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\UsesCommittedMembershipDatabase;
use Tests\TestCase;

abstract class MembershipContextMutationTestFixtures extends TestCase
{
    use CreatesUserWithRole;
    use UsesCommittedMembershipDatabase;
}

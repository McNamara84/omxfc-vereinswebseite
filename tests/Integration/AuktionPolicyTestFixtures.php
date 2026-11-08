<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Policies\AuktionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait AuktionPolicyTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private AuktionPolicy $policy;
}

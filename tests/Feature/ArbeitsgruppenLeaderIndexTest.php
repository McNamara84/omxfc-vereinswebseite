<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

pest()->extend(TestCase::class)->use(ArbeitsgruppenLeaderIndexTestFixtures::class);

test('leader can view own groups', function (): void {
    [$leader] = $this->groupWithLeader();
    $this->actingAs($leader)->get('/ag')->assertOk()->assertSee('AG Test');
});

test('non leader gets forbidden', function (): void {
    [$leader] = $this->groupWithLeader();
    $other = $this->member();
    $this->actingAs($other)->get('/ag')->assertForbidden();
});

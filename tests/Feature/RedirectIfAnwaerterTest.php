<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RedirectIfAnwaerterTestFixtures::class);

test('anwaerter is redirected to login', function (): void {
    $user = $this->createUser(Role::Anwaerter);
    $this->actingAs($user);
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('regular member can access route', function (): void {
    $user = $this->createUser(Role::Mitglied);
    $this->actingAs($user);
    $response = $this->get('/dashboard');
    $response->assertOk();
});

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(LoginResponseTestFixtures::class);

test('anwaerter is logged out on login', function (): void {
    $user = $this->createMember(Role::Anwaerter);
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertRedirect(route('login', absolute: false));
    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('regular member is redirected to dashboard', function (): void {
    $user = $this->createMember();
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

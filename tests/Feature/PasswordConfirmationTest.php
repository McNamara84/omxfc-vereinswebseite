<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('confirm password screen can be rendered', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $response = $this->actingAs($user)->get('/user/confirm-password');
    $response->assertStatus(200);
});

test('password can be confirmed', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/user/confirm-password', ['password' => 'password']);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/user/confirm-password', ['password' => 'wrong-password']);
    $response->assertSessionHasErrors();
});

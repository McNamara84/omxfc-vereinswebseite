<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
dataset('AuthenticationTest.invalidUsernames', static function (): iterable {
    yield 'null' => [null];
    yield 'array' => [['invalid@example.test']];
    yield 'integer' => [1234];
});
test('invalid username types are validated without php deprecations', function (mixed $username): void {
    $this->postJson('/login', ['email' => $username, 'password' => 'invalid-password'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();
})->with('AuthenticationTest.invalidUsernames');
test('login screen can be rendered', function (): void {
    $response = $this->withoutVite()->get('/login');
    $response->assertStatus(200);
});
test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
test('users can not authenticate with invalid password', function (): void {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $this->assertGuest();
});
test('login form has accessible structure and test ids', function (): void {
    $response = $this->withoutVite()->get('/login');
    // data-testid-Attribute für E2E-Tests
    $response->assertSee('data-testid="login-email"', false);
    $response->assertSee('data-testid="login-password"', false);
    $response->assertSee('data-testid="login-submit"', false);
    // Semantische Struktur: H1-Heading, Label-Legenden, Formular-Action
    $html = $response->getContent();
    expect($html)->toContain('<h1')
        ->toContain('Login')
        ->toMatch('/<(legend|span)[^>]*>\s*E-Mail/si')
        ->toMatch('/<legend[^>]*>\s*Passwort/si')
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="current-password"');
});
test('login validation errors', function (): void {
    $response = $this->from('/login')->post('/login', []);
    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['email', 'password']);
});

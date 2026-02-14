<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_form_has_accessible_structure_and_test_ids(): void
    {
        $response = $this->get('/login');

        // data-testid-Attribute für E2E-Tests
        $response->assertSee('data-testid="login-email-input"', false);
        $response->assertSee('data-testid="login-password-input"', false);
        $response->assertSee('data-testid="login-submit-button"', false);

        // Semantische Struktur: H1-Heading, Label-Legenden, Formular-Action
        $html = $response->getContent();
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Login', $html);
        $this->assertMatchesRegularExpression('/<legend[^>]*>\s*E-Mail/si', $html);
        $this->assertMatchesRegularExpression('/<legend[^>]*>\s*Passwort/si', $html);
        $this->assertStringContainsString('autocomplete="username"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->from('/login')->post('/login', []);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email', 'password']);
    }
}

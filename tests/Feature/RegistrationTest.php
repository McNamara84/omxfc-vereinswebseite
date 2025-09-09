<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;
use App\Models\User;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post(route('mitglied.store'), [
            'vorname' => 'Test',
            'nachname' => 'User',
            'strasse' => 'Teststraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            // Use a unique email to avoid clashing with seeded users
            'mail' => 'newuser@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12.00,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertGuest();
    }

    public function test_email_must_be_unique_when_registering(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->from('/register')->post(route('mitglied.store'), [
            'vorname' => 'Test',
            'nachname' => 'User',
            'strasse' => 'Teststraße',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'mail' => 'duplicate@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12.00,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('mail');
    }

    public function test_validation_errors_when_required_fields_missing(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->from('/mitglied-werden')->post(route('mitglied.store'), [
            'vorname' => '',
            'nachname' => '',
            'strasse' => '',
            'hausnummer' => '',
            'plz' => '',
            'stadt' => '',
            'land' => '',
            'mail' => '',
            'passwort' => '',
            'passwort_confirmation' => '',
            'mitgliedsbeitrag' => '',
        ]);

        $response->assertRedirect('/mitglied-werden');
        $response->assertSessionHasErrors([
            'vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort', 'mitgliedsbeitrag'
        ]);
    }
}

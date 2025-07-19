<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

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
            'mail' => 'test@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12.00,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertGuest();
    }
}

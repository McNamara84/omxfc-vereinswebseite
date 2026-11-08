<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('registration screen can be rendered', function (): void {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }
    $response = $this->get('/register');
    $response->assertStatus(200);
    $response->assertSee('Mindestens 8 Zeichen.');
    $response->assertSee('Verwende mindestens 8 Zeichen und ein nur hier genutztes Passwort.');
});

test('new users can register', function (): void {
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
        'mitgliedsbeitrag' => 12.0,
        'telefon' => null,
        'verein_gefunden' => null,
    ]);
    $response->assertJson(['success' => true]);
    $this->assertGuest();
});

test('email must be unique when registering', function (): void {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }
    User::factory()->create(['email' => 'duplicate@example.com']);
    $response = $this->from('/register')->post(route('mitglied.store'), ['vorname' => 'Test', 'nachname' => 'User', 'strasse' => 'Teststraße', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'mail' => 'duplicate@example.com', 'passwort' => 'password', 'passwort_confirmation' => 'password', 'mitgliedsbeitrag' => 12.0, 'telefon' => null, 'verein_gefunden' => null]);
    $response->assertRedirect('/register');
    $response->assertSessionHasErrors('mail');
});

test('validation errors when required fields missing', function (): void {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }
    $response = $this->from('/mitglied-werden')->post(route('mitglied.store'), ['vorname' => '', 'nachname' => '', 'strasse' => '', 'hausnummer' => '', 'plz' => '', 'stadt' => '', 'land' => '', 'mail' => '', 'passwort' => '', 'passwort_confirmation' => '', 'mitgliedsbeitrag' => '']);
    $response->assertRedirect('/mitglied-werden');
    $response->assertSessionHasErrors(['vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort', 'mitgliedsbeitrag']);
});

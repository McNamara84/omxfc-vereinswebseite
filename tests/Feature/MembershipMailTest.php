<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('application triggers mail and creates user', function (): void {
    Mail::fake();
    $response = $this->post(route('mitglied.store'), ['vorname' => 'Test', 'nachname' => 'Applicant', 'strasse' => 'Teststr', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'mail' => 'applicant@example.com', 'passwort' => 'password', 'passwort_confirmation' => 'password', 'mitgliedsbeitrag' => 12, 'telefon' => null, 'verein_gefunden' => null]);
    $response->assertJson(['success' => true]);
    Mail::assertQueued(MitgliedAntragEingereicht::class, fn ($mail) => $mail->user->email === 'applicant@example.com');
    $this->assertDatabaseHas('users', ['email' => 'applicant@example.com']);
    $this->assertDatabaseHas('team_user', ['role' => Role::Anwaerter->value]);
});

test('application fails when first name missing', function (): void {
    Mail::fake();
    $response = $this->postJson(route('mitglied.store'), ['vorname' => '', 'nachname' => 'Applicant', 'strasse' => 'Teststr', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'mail' => 'applicant@example.com', 'passwort' => 'password', 'passwort_confirmation' => 'password', 'mitgliedsbeitrag' => 12, 'telefon' => null, 'verein_gefunden' => null]);
    $response->assertStatus(422);
    Mail::assertNothingSent();
    $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    $this->assertDatabaseMissing('team_user', ['role' => Role::Anwaerter->value]);
});

test('application fails when email already exists', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'applicant@example.com']);
    $response = $this->postJson(route('mitglied.store'), ['vorname' => 'Test', 'nachname' => 'Applicant', 'strasse' => 'Teststr', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'mail' => 'applicant@example.com', 'passwort' => 'password', 'passwort_confirmation' => 'password', 'mitgliedsbeitrag' => 12, 'telefon' => null, 'verein_gefunden' => null]);
    $response->assertStatus(422);
    Mail::assertNothingSent();
    expect(User::where('email', 'applicant@example.com')->count())->toBe(1);
    $this->assertDatabaseMissing('team_user', ['role' => Role::Anwaerter->value]);
});

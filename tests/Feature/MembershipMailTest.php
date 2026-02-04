<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembershipMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_triggers_mail_and_creates_user(): void
    {
        Mail::fake();

        $response = $this->post(route('mitglied.store'), [
            'vorname' => 'Test',
            'nachname' => 'Applicant',
            'strasse' => 'Teststr',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'mail' => 'applicant@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertJson(['success' => true]);

        Mail::assertQueued(MitgliedAntragEingereicht::class, function ($mail) {
            return $mail->user->email === 'applicant@example.com';
        });

        $this->assertDatabaseHas('users', ['email' => 'applicant@example.com']);
        $this->assertDatabaseHas('team_user', ['role' => Role::Anwaerter->value]);
    }

    public function test_application_fails_when_first_name_missing(): void
    {
        Mail::fake();

        $response = $this->postJson(route('mitglied.store'), [
            'vorname' => '',
            'nachname' => 'Applicant',
            'strasse' => 'Teststr',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'mail' => 'applicant@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertStatus(422);
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
        $this->assertDatabaseMissing('team_user', ['role' => Role::Anwaerter->value]);
    }

    public function test_application_fails_when_email_already_exists(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'applicant@example.com']);

        $response = $this->postJson(route('mitglied.store'), [
            'vorname' => 'Test',
            'nachname' => 'Applicant',
            'strasse' => 'Teststr',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'mail' => 'applicant@example.com',
            'passwort' => 'password',
            'passwort_confirmation' => 'password',
            'mitgliedsbeitrag' => 12,
            'telefon' => null,
            'verein_gefunden' => null,
        ]);

        $response->assertStatus(422);
        Mail::assertNothingSent();
        $this->assertSame(1, User::where('email', 'applicant@example.com')->count());
        $this->assertDatabaseMissing('team_user', ['role' => Role::Anwaerter->value]);
    }
}

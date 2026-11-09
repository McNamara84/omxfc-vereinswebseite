<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MitgliedWerdenForm;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('form renders on page', function (): void {
    $response = $this->get('/mitglied-werden');
    $response->assertOk();
    $response->assertSeeLivewire(MitgliedWerdenForm::class);
});

test('successful registration creates user and team', function (): void {
    Mail::fake();
    Livewire::test(MitgliedWerdenForm::class)->set('vorname', 'Max')->set('nachname', 'Mustermann')->set('strasse', 'Teststraße')->set('hausnummer', '42')->set('plz', '12345')->set('stadt', 'Berlin')->set('land', 'Deutschland')->set('mail', 'max.neu@example.com')->set('passwort', 'geheim123')->set('passwort_confirmation', 'geheim123')->set('mitgliedsbeitrag', 24)->set('satzung_check', true)->call('submit')->assertRedirect(route('mitglied.werden.erfolgreich'));
    $this->assertDatabaseHas('users', ['email' => 'max.neu@example.com', 'vorname' => 'Max', 'nachname' => 'Mustermann']);
    $user = User::where('email', 'max.neu@example.com')->first();
    $team = Team::where('name', 'Mitglieder')->first();
    expect($team)->not->toBeNull()
        ->and($user->current_team_id)->toEqual($team->id)
        ->and($team->users()->where('user_id', $user->id)->wherePivot('role', Role::Anwaerter->value)->exists())->toBeTrue();
});

test('registration sends confirmation mail', function (): void {
    Mail::fake();
    Livewire::test(MitgliedWerdenForm::class)->set('vorname', 'Anna')->set('nachname', 'Testerin')->set('strasse', 'Mailweg')->set('hausnummer', '1')->set('plz', '54321')->set('stadt', 'Hamburg')->set('land', 'Deutschland')->set('mail', 'anna@example.com')->set('passwort', 'sicher456')->set('passwort_confirmation', 'sicher456')->set('satzung_check', true)->call('submit');
    Mail::assertQueued(MitgliedAntragEingereicht::class, fn ($mail) => $mail->hasTo('anna@example.com'));
});

test('satzung check required', function (): void {
    Livewire::test(MitgliedWerdenForm::class)->set('vorname', 'Max')->set('nachname', 'Mustermann')->set('strasse', 'Teststraße')->set('hausnummer', '42')->set('plz', '12345')->set('stadt', 'Berlin')->set('land', 'Deutschland')->set('mail', 'max2@example.com')->set('passwort', 'geheim123')->set('passwort_confirmation', 'geheim123')->set('satzung_check', false)->call('submit')->assertHasErrors(['satzung_check']);
});

test('password confirmation must match', function (): void {
    Livewire::test(MitgliedWerdenForm::class)->set('vorname', 'Max')->set('nachname', 'Mustermann')->set('strasse', 'Teststraße')->set('hausnummer', '42')->set('plz', '12345')->set('stadt', 'Berlin')->set('land', 'Deutschland')->set('mail', 'max3@example.com')->set('passwort', 'geheim123')->set('passwort_confirmation', 'falsch456')->set('satzung_check', true)->call('submit')->assertHasErrors(['passwort']);
});

test('email must be unique', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    Livewire::test(MitgliedWerdenForm::class)->set('vorname', 'Max')->set('nachname', 'Mustermann')->set('strasse', 'Teststraße')->set('hausnummer', '42')->set('plz', '12345')->set('stadt', 'Berlin')->set('land', 'Deutschland')->set('mail', 'existing@example.com')->set('passwort', 'geheim123')->set('passwort_confirmation', 'geheim123')->set('satzung_check', true)->call('submit')->assertHasErrors(['mail']);
});

test('realtime validation on blur', function (): void {
    Livewire::test(MitgliedWerdenForm::class)->set('mail', 'keine-email')->assertHasErrors(['mail']);
});

test('required fields validated', function (): void {
    Livewire::test(MitgliedWerdenForm::class)->set('satzung_check', true)->call('submit')->assertOnlyHasErrors(['vorname', 'nachname', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'mail', 'passwort']);
});

test('mitgliedsbeitrag must be in range', function (): void {
    Livewire::test(MitgliedWerdenForm::class)->set('mitgliedsbeitrag', 5)->set('vorname', 'Max')->set('nachname', 'Test')->set('strasse', 'S')->set('hausnummer', '1')->set('plz', '12345')->set('stadt', 'X')->set('land', 'Deutschland')->set('mail', 'beitrag@example.com')->set('passwort', 'geheim123')->set('passwort_confirmation', 'geheim123')->set('satzung_check', true)->call('submit')->assertOnlyHasErrors(['mitgliedsbeitrag']);
});

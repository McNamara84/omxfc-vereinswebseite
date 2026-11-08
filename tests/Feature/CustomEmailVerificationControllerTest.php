<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AntragAnAdmin;
use App\Mail\AntragAnVorstand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('email can be verified and mails sent', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    $verificationUrl = URL::temporarySignedRoute('verification.verify.de', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $response = $this->get($verificationUrl);
    $response->assertRedirect(route('mitglied.werden.bestaetigt', absolute: false));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertQueued(AntragAnVorstand::class, fn ($mail) => $mail->hasTo('vorstand@maddrax-fanclub.de') && $mail->hasTo('kassenwart@maddrax-fanclub.de'));
    Mail::assertQueued(AntragAnAdmin::class, fn ($mail) => $mail->hasTo('info@maddraxikon.com'));
});

test('verification fails with invalid hash', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    $verificationUrl = URL::temporarySignedRoute('verification.verify.de', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('wrong-email')]);
    $response = $this->get($verificationUrl);
    $response->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Mail::assertNothingSent();
});

test('already verified user gets redirected with message', function (): void {
    Mail::fake();
    $user = User::factory()->create();
    $verificationUrl = URL::temporarySignedRoute('verification.verify.de', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $response = $this->get($verificationUrl);
    $response->assertRedirect(route('mitglied.werden.bestaetigt', absolute: false));
    $response->assertSessionHas('status', 'Deine E-Mail-Adresse wurde bereits verifiziert.');
    Mail::assertNothingSent();
});

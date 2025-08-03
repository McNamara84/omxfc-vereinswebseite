<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Mail\AntragAnVorstand;
use App\Mail\AntragAnAdmin;

class CustomEmailVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_can_be_verified_and_mails_sent(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify.de',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('mitglied.werden.bestaetigt', absolute: false));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Mail::assertSent(AntragAnVorstand::class, fn ($mail) => $mail->hasTo('vorstand@maddrax-fanclub.de'));
        Mail::assertSent(AntragAnAdmin::class, fn ($mail) => $mail->hasTo('holgerehrmann@gmail.com'));
    }

    public function test_verification_fails_with_invalid_hash(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify.de',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertNothingSent();
    }

    public function test_already_verified_user_gets_redirected_with_message(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify.de',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('mitglied.werden.bestaetigt', absolute: false));
        $response->assertSessionHas('status', 'Deine E-Mail-Adresse wurde bereits verifiziert.');
        Mail::assertNothingSent();
    }
}

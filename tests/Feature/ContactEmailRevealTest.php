<?php

namespace Tests\Feature;

use App\Http\Livewire\ContactEmailReveal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ContactEmailRevealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.contact.email' => 'kontakt@maddrax-fanclub.de',
            'services.hcaptcha.enabled' => true,
            'services.hcaptcha.sitekey' => 'test-sitekey',
            'services.hcaptcha.secret' => 'test-secret',
            'services.hcaptcha.bypass_token' => null,
        ]);
    }

    public function test_reveal_route_requires_valid_signature(): void
    {
        $this->getJson('/kontakt/email/reveal')->assertForbidden();
    }

    public function test_reveal_route_returns_email_with_valid_signature(): void
    {
        $signedUrl = URL::temporarySignedRoute('contact.email.reveal', now()->addMinutes(5));

        $this->getJson($signedUrl)
            ->assertOk()
            ->assertJson(['email' => 'kontakt@maddrax-fanclub.de']);
    }

    public function test_reveal_route_is_rate_limited(): void
    {
        config(['services.hcaptcha.rate_limit_per_minute' => 2]);
        RateLimiter::clear('reveal-contact-email|127.0.0.1');

        $signedUrl = URL::temporarySignedRoute('contact.email.reveal', now()->addMinutes(5));

        $this->getJson($signedUrl)->assertOk();
        $this->getJson($signedUrl)->assertOk();
        $this->getJson($signedUrl)->assertStatus(429);
    }

    public function test_component_reveals_email_after_successful_captcha(): void
    {
        Http::fake([
            'https://hcaptcha.com/*' => Http::response([
                'success' => true,
                'score' => 0.9,
            ], 200),
        ]);

        Livewire::test(ContactEmailReveal::class)
            ->set('hcaptchaToken', 'valid-token')
            ->call('reveal')
            ->assertSet('revealed', true)
            ->assertSet('email', 'kontakt@maddrax-fanclub.de')
            ->assertDispatched('email-revealed', email: 'kontakt@maddrax-fanclub.de');
    }

    public function test_component_shows_error_when_captcha_fails(): void
    {
        Http::fake([
            'https://hcaptcha.com/*' => Http::response([
                'success' => false,
            ], 200),
        ]);

        Livewire::test(ContactEmailReveal::class)
            ->set('hcaptchaToken', 'invalid-token')
            ->call('reveal')
            ->assertHasErrors(['hcaptchaToken'])
            ->assertSet('revealed', false)
            ->assertNotDispatched('email-revealed');
    }
}

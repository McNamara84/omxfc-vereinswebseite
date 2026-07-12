<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class TwoFactorChallengeViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticator_code_field_uses_daisyui_otp_component(): void
    {
        $this->withoutVite();

        $html = view('auth.two-factor-challenge', ['errors' => new ViewErrorBag])->render();
        $crawler = new Crawler($html);
        $otp = $crawler->filter('[data-testid="two-factor-code-otp"]');

        $this->assertCount(1, $otp);
        $this->assertStringContainsString('otp', $otp->attr('class') ?? '');
        $this->assertStringContainsString('otp-joined', $otp->attr('class') ?? '');
        $this->assertStringContainsString('otp-primary', $otp->attr('class') ?? '');
        $this->assertCount(6, $otp->filter('span[aria-hidden="true"]'));

        $codeInput = $otp->filter('input#code[name="code"][data-testid="two-factor-code"]');

        $this->assertCount(1, $codeInput);
        $this->assertSame('numeric', $codeInput->attr('inputmode'));
        $this->assertSame('[0-9]*', $codeInput->attr('pattern'));
        $this->assertSame('6', $codeInput->attr('minlength'));
        $this->assertSame('6', $codeInput->attr('maxlength'));
        $this->assertSame('one-time-code', $codeInput->attr('autocomplete'));
        $this->assertSame('two-factor-code-label', $codeInput->attr('aria-labelledby'));

        $this->assertCount(1, $crawler->filter('input#recovery_code[name="recovery_code"][data-testid="two-factor-recovery-code"]'));
    }
}

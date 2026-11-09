<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('authenticator code field uses daisyui otp component', function (): void {
    $this->withoutVite();
    $html = view('auth.two-factor-challenge', ['errors' => new ViewErrorBag])->render();
    $crawler = new Crawler($html);
    $otp = $crawler->filter('[data-testid="two-factor-code-otp"]');
    expect($otp)->toHaveCount(1)
        ->and($otp->attr('class') ?? '')->toContain('otp')
        ->and($otp->attr('class') ?? '')->toContain('otp-joined')
        ->and($otp->attr('class') ?? '')->toContain('otp-primary')
        ->and($otp->attr('aria-label'))->toBeNull()
        ->and($otp->filter('span[aria-hidden="true"]'))->toHaveCount(6);
    $codeLabel = $crawler->filter('#two-factor-code-label');
    expect($codeLabel)->toHaveCount(1)
        ->and($codeLabel->text())->toContain('Code')
        ->and($codeLabel->filter('.sr-only')->text())->toBe('- Sechsstelligen Authentifizierungscode eingeben');
    $codeInput = $otp->filter('input#code[name="code"][data-testid="two-factor-code"]');
    expect($codeInput)->toHaveCount(1)
        ->and($codeInput->attr('inputmode'))->toBe('numeric')
        ->and($codeInput->attr('pattern'))->toBe('[0-9]*')
        ->and($codeInput->attr('minlength'))->toBe('6')
        ->and($codeInput->attr('maxlength'))->toBe('6')
        ->and($codeInput->attr('autocomplete'))->toBe('one-time-code')
        ->and($codeInput->attr('aria-labelledby'))->toBe('two-factor-code-label')
        ->and($crawler->filter('input#recovery_code[name="recovery_code"][data-testid="two-factor-recovery-code"]'))->toHaveCount(1);
});

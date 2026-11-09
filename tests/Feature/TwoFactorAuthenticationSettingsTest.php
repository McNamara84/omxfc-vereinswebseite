<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm;
use Livewire\Livewire;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('two factor authentication can be enabled', function (): void {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }
    $this->actingAs($user = User::factory()->create());
    $this->withSession(['auth.password_confirmed_at' => time()]);
    Livewire::test(TwoFactorAuthenticationForm::class)->call('enableTwoFactorAuthentication');
    $user = $user->fresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);
});

test('recovery codes can be regenerated', function (): void {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }
    $this->actingAs($user = User::factory()->create());
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(TwoFactorAuthenticationForm::class)->call('enableTwoFactorAuthentication')->call('regenerateRecoveryCodes');
    $user = $user->fresh();
    $component->call('regenerateRecoveryCodes');
    expect($user->recoveryCodes())->toHaveCount(8)
        ->and(array_diff($user->recoveryCodes(), $user->fresh()->recoveryCodes()))->toHaveCount(8);
});

test('two factor confirmation uses daisyui otp component', function (): void {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }
    $this->actingAs(User::factory()->create());
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(TwoFactorAuthenticationForm::class)->call('enableTwoFactorAuthentication');
    $crawler = new Crawler($component->html());
    $otp = $crawler->filter('[data-testid="two-factor-confirmation-code-otp"]');
    expect($otp)->toHaveCount(1)
        ->and($otp->attr('class') ?? '')->toContain('otp')
        ->and($otp->attr('class') ?? '')->toContain('otp-joined')
        ->and($otp->attr('class') ?? '')->toContain('otp-primary')
        ->and($otp->filter('span[aria-hidden="true"]'))->toHaveCount(6);
    $codeInput = $otp->filter('input#code[name="code"][data-testid="two-factor-confirmation-code"]');
    expect($codeInput)->toHaveCount(1)
        ->and($codeInput->attr('inputmode'))->toBe('numeric')
        ->and($codeInput->attr('pattern'))->toBe('[0-9]*')
        ->and($codeInput->attr('minlength'))->toBe('6')
        ->and($codeInput->attr('maxlength'))->toBe('6')
        ->and($codeInput->attr('autocomplete'))->toBe('one-time-code')
        ->and($codeInput->attr('aria-labelledby'))->toBe('two-factor-confirmation-code-label');
});

test('two factor authentication can be disabled', function (): void {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two factor authentication is not enabled.');
    }
    $this->actingAs($user = User::factory()->create());
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(TwoFactorAuthenticationForm::class)->call('enableTwoFactorAuthentication');
    expect($user->fresh()->two_factor_secret)->not->toBeNull();
    $component->call('disableTwoFactorAuthentication');
    expect($user->fresh()->two_factor_secret)->toBeNull();
});

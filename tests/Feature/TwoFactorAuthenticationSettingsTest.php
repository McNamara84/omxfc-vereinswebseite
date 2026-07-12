<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm;
use Livewire\Livewire;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class TwoFactorAuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_authentication_can_be_enabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $this->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::test(TwoFactorAuthenticationForm::class)
            ->call('enableTwoFactorAuthentication');

        $user = $user->fresh();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::test(TwoFactorAuthenticationForm::class)
            ->call('enableTwoFactorAuthentication')
            ->call('regenerateRecoveryCodes');

        $user = $user->fresh();

        $component->call('regenerateRecoveryCodes');

        $this->assertCount(8, $user->recoveryCodes());
        $this->assertCount(8, array_diff($user->recoveryCodes(), $user->fresh()->recoveryCodes()));
    }

    public function test_two_factor_confirmation_uses_daisyui_otp_component(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $this->actingAs(User::factory()->create());

        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::test(TwoFactorAuthenticationForm::class)
            ->call('enableTwoFactorAuthentication');

        $crawler = new Crawler($component->html());
        $otp = $crawler->filter('[data-testid="two-factor-confirmation-code-otp"]');

        $this->assertCount(1, $otp);
        $this->assertStringContainsString('otp', $otp->attr('class') ?? '');
        $this->assertStringContainsString('otp-joined', $otp->attr('class') ?? '');
        $this->assertStringContainsString('otp-primary', $otp->attr('class') ?? '');
        $this->assertCount(6, $otp->filter('span[aria-hidden="true"]'));

        $codeInput = $otp->filter('input#code[name="code"][data-testid="two-factor-confirmation-code"]');

        $this->assertCount(1, $codeInput);
        $this->assertSame('numeric', $codeInput->attr('inputmode'));
        $this->assertSame('[0-9]*', $codeInput->attr('pattern'));
        $this->assertSame('6', $codeInput->attr('minlength'));
        $this->assertSame('6', $codeInput->attr('maxlength'));
        $this->assertSame('one-time-code', $codeInput->attr('autocomplete'));
        $this->assertSame('two-factor-confirmation-code-label', $codeInput->attr('aria-labelledby'));
    }

    public function test_two_factor_authentication_can_be_disabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two factor authentication is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::test(TwoFactorAuthenticationForm::class)
            ->call('enableTwoFactorAuthentication');

        $this->assertNotNull($user->fresh()->two_factor_secret);

        $component->call('disableTwoFactorAuthentication');

        $this->assertNull($user->fresh()->two_factor_secret);
    }
}

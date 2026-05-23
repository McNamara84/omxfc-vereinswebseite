<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_does_not_change_existing_verified_timestamp(): void
    {
        // Create a verified user with a specific timestamp
        $timestamp = now()->subDay();
        $user = User::factory()->create([
            'email_verified_at' => $timestamp,
        ]);

        // Dispatching a password reset should not change the timestamp
        event(new PasswordReset($user));

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($timestamp->getTimestamp(), $user->email_verified_at->getTimestamp());
    }

    public function test_social_image_defaults_to_logo_when_no_image_provided(): void
    {
        $this->withoutVite();

        $view = view('layouts.guest', ['slot' => '']);
        $view->render();

        $socialImage = $view->getData()['socialImage'];

        $this->assertStringContainsString('omxfc-logo', $socialImage);
        $this->assertStringEndsWith('.png', $socialImage);
    }

    public function test_social_image_uses_asset_for_relative_path(): void
    {
        $this->withoutVite();

        $view = view('layouts.guest', ['slot' => '', 'image' => 'images/custom.png']);
        $view->render();

        $this->assertSame(asset('images/custom.png'), $view->getData()['socialImage']);
    }

    public function test_social_image_uses_provided_absolute_url(): void
    {
        $this->withoutVite();

        $url = 'https://example.com/social.png';
        $view = view('layouts.guest', ['slot' => '', 'image' => $url]);
        $view->render();

        $this->assertSame($url, $view->getData()['socialImage']);
    }

    public function test_app_layout_skips_vite_assets_during_unit_tests(): void
    {
        Config::set('app.testing_minimal_layout', true);

        $html = view('layouts.app', ['slot' => 'Testinhalt'])->render();

        $this->assertStringContainsString('Testinhalt', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);
        $this->assertStringNotContainsString('resources/js/app.js', $html);
    }

    public function test_testing_environment_ignores_standard_vite_hot_file(): void
    {
        $hotPath = public_path('hot');
        $originalHotExists = is_file($hotPath);
        $originalHotContents = $originalHotExists ? file_get_contents($hotPath) : null;

        file_put_contents($hotPath, 'http://127.0.0.1:5999');

        try {
            $html = Blade::render("@vite(['resources/css/app.css'])");

            $this->assertStringNotContainsString('http://127.0.0.1:5999', $html);
            $this->assertStringContainsString('/build/assets/', $html);
        } finally {
            if ($originalHotExists) {
                file_put_contents($hotPath, $originalHotContents ?: '');
            } elseif (is_file($hotPath)) {
                unlink($hotPath);
            }
        }
    }

    public function test_playwright_docker_environment_uses_dedicated_vite_hot_file(): void
    {
        $hotPath = public_path('playwright.hot');
        $originalHotExists = is_file($hotPath);
        $originalHotContents = $originalHotExists ? file_get_contents($hotPath) : null;
        $originalPlaywrightUseDocker = getenv('PLAYWRIGHT_USE_DOCKER');

        file_put_contents($hotPath, 'http://127.0.0.1:5173');
        putenv('PLAYWRIGHT_USE_DOCKER=1');
        $_ENV['PLAYWRIGHT_USE_DOCKER'] = '1';
        $_SERVER['PLAYWRIGHT_USE_DOCKER'] = '1';

        try {
            $this->refreshApplication();

            $html = Blade::render("@vite(['resources/css/app.css'])");

            $this->assertStringContainsString('http://127.0.0.1:5173', $html);
            $this->assertStringContainsString('resources/css/app.css', $html);
        } finally {
            if ($originalPlaywrightUseDocker === false) {
                putenv('PLAYWRIGHT_USE_DOCKER');
                unset($_ENV['PLAYWRIGHT_USE_DOCKER'], $_SERVER['PLAYWRIGHT_USE_DOCKER']);
            } else {
                putenv("PLAYWRIGHT_USE_DOCKER={$originalPlaywrightUseDocker}");
                $_ENV['PLAYWRIGHT_USE_DOCKER'] = $originalPlaywrightUseDocker;
                $_SERVER['PLAYWRIGHT_USE_DOCKER'] = $originalPlaywrightUseDocker;
            }

            if ($originalHotExists) {
                file_put_contents($hotPath, $originalHotContents ?: '');
            } elseif (is_file($hotPath)) {
                unlink($hotPath);
            }

            $this->refreshApplication();
        }
    }
}

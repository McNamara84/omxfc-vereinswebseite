<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_VITE_BUILD_DIRECTORY = 'build-app-service-provider-test';

    private const TEST_VITE_ASSET = 'assets/app-service-provider-test.css';

    private const TEST_VITE_SCRIPT_ASSET = 'assets/app-service-provider-test.js';

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

    public function test_app_layout_skips_vite_assets_for_minimal_test_layout(): void
    {
        Config::set('app.testing_minimal_layout', true);

        $this->assertTrue(config('app.testing_skip_vite_assets'));

        $html = view('layouts.app', ['slot' => 'Testinhalt'])->render();

        $this->assertStringContainsString('Testinhalt', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);
        $this->assertStringNotContainsString('resources/js/app.js', $html);
    }

    public function test_app_layout_keeps_vite_assets_without_minimal_test_layout(): void
    {
        $this->withTemporaryViteManifest(function (): void {
            $html = view('layouts.app', ['slot' => 'Testinhalt'])->render();

            $this->assertStringContainsString('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_ASSET, $html);
            $this->assertStringContainsString('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_SCRIPT_ASSET, $html);
        });
    }

    public function test_testing_environment_ignores_standard_vite_hot_file(): void
    {
        $hotPath = public_path('hot');
        $originalHotExists = is_file($hotPath);
        $originalHotContents = $originalHotExists ? file_get_contents($hotPath) : null;

        $this->withTemporaryViteManifest(function () use ($hotPath, $originalHotExists, $originalHotContents): void {
            file_put_contents($hotPath, 'http://127.0.0.1:5999');

            try {
                $html = Blade::render("@vite(['resources/css/app.css'])");

                $this->assertStringNotContainsString('http://127.0.0.1:5999', $html);
                $this->assertStringContainsString('/'.self::TEST_VITE_BUILD_DIRECTORY.'/'.self::TEST_VITE_ASSET, $html);
            } finally {
                if ($originalHotExists) {
                    file_put_contents($hotPath, $originalHotContents ?: '');
                } elseif (is_file($hotPath)) {
                    unlink($hotPath);
                }
            }
        });
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

    private function withTemporaryViteManifest(callable $callback): void
    {
        $buildDirectory = public_path(self::TEST_VITE_BUILD_DIRECTORY);
        $assetsDirectory = $buildDirectory.'/assets';
        $manifestPath = $buildDirectory.'/manifest.json';
        $assetPath = $buildDirectory.'/'.self::TEST_VITE_ASSET;
        $scriptAssetPath = $buildDirectory.'/'.self::TEST_VITE_SCRIPT_ASSET;

        $buildDirectoryCreated = ! is_dir($buildDirectory);
        $assetsDirectoryCreated = ! is_dir($assetsDirectory);
        $originalManifestExists = is_file($manifestPath);
        $originalManifestContents = $originalManifestExists ? file_get_contents($manifestPath) : null;
        $originalAssetExists = is_file($assetPath);
        $originalAssetContents = $originalAssetExists ? file_get_contents($assetPath) : null;
        $originalScriptAssetExists = is_file($scriptAssetPath);
        $originalScriptAssetContents = $originalScriptAssetExists ? file_get_contents($scriptAssetPath) : null;

        if ($buildDirectoryCreated) {
            mkdir($buildDirectory, 0777, true);
        }

        if ($assetsDirectoryCreated) {
            mkdir($assetsDirectory, 0777, true);
        }

        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => self::TEST_VITE_ASSET,
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => self::TEST_VITE_SCRIPT_ASSET,
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($assetPath, 'body { display: block; }');
        file_put_contents($scriptAssetPath, 'console.log("app-service-provider-test");');

        clearstatcache(true, $manifestPath);
        clearstatcache(true, $assetPath);
        clearstatcache(true, $scriptAssetPath);
        Vite::useBuildDirectory(self::TEST_VITE_BUILD_DIRECTORY);

        try {
            $callback();
        } finally {
            Vite::useBuildDirectory('build');

            if ($originalManifestExists) {
                file_put_contents($manifestPath, $originalManifestContents ?: '');
            } elseif (is_file($manifestPath)) {
                unlink($manifestPath);
            }

            if ($originalAssetExists) {
                file_put_contents($assetPath, $originalAssetContents ?: '');
            } elseif (is_file($assetPath)) {
                unlink($assetPath);
            }

            if ($originalScriptAssetExists) {
                file_put_contents($scriptAssetPath, $originalScriptAssetContents ?: '');
            } elseif (is_file($scriptAssetPath)) {
                unlink($scriptAssetPath);
            }

            if ($assetsDirectoryCreated) {
                @rmdir($assetsDirectory);
            }

            if ($buildDirectoryCreated) {
                @rmdir($buildDirectory);
            }

            clearstatcache(true, $manifestPath);
            clearstatcache(true, $assetPath);
            clearstatcache(true, $scriptAssetPath);
        }
    }
}

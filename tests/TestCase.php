<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Illuminate\Testing\TestView;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

abstract class TestCase extends BaseTestCase
{
    private static bool $bladeIconsManifestReady = false;

    /**
     * Default coordinates used for stubbed Nominatim responses (Munich).
     */
    protected const DEFAULT_LAT = '48.0';

    protected const DEFAULT_LON = '11.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLivewireAssertions();
        $this->withoutMiddleware(PreventRequestForgery::class);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        config(['logging.default' => 'null']);

        $this->ensureBladeIconsManifest();

        // Cache leeren, um sicherzustellen, dass Tests isoliert laufen
        Cache::flush();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => self::DEFAULT_LAT,
                'lon' => self::DEFAULT_LON,
            ]], 200),
        ]);

        // Seed the database so default entities such as the "Mitglieder" team
        // are available to the tests. Seeding occurs after HTTP calls are
        // faked to avoid real network requests.
        $this->seed();
    }

    private function ensureBladeIconsManifest(): void
    {
        if (self::$bladeIconsManifestReady) {
            return;
        }

        $manifestPath = app()->bootstrapPath('cache/blade-icons.php');

        if (! is_file($manifestPath)) {
            Artisan::call('icons:cache');
        }

        self::$bladeIconsManifestReady = true;
    }

    private function registerLivewireAssertions(): void
    {
        if (! TestResponse::hasMacro('assertSeeLivewire')) {
            TestResponse::macro('assertSeeLivewire', function ($component) {
                if (is_subclass_of($component, Component::class)) {
                    $component = app('livewire.factory')->resolveComponentName($component);
                }

                $escapedComponentName = trim(htmlspecialchars(json_encode(['name' => $component])), '{}');

                
                \PHPUnit\Framework\Assert::assertStringContainsString(
                    $escapedComponentName,
                    $this->getContent(),
                    'Cannot find Livewire component ['.$component.'] rendered on page.'
                );

                return $this;
            });
        }

        if (class_exists(TestView::class) && ! TestView::hasMacro('assertSeeLivewire')) {
            TestView::macro('assertSeeLivewire', function ($component) {
                if (is_subclass_of($component, Component::class)) {
                    $component = app('livewire.factory')->resolveComponentName($component);
                }

                $escapedComponentName = trim(htmlspecialchars(json_encode(['name' => $component])), '{}');

                \PHPUnit\Framework\Assert::assertStringContainsString(
                    $escapedComponentName,
                    $this->rendered,
                    'Cannot find Livewire component ['.$component.'] rendered on page.'
                );

                return $this;
            });
        }
    }
}

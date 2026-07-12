<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TestCaseBladeIconsManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_blade_icons_manifest_throws_when_cache_generation_fails(): void
    {
        $reflection = new \ReflectionClass(TestCase::class);
        $readyProperty = $reflection->getProperty('bladeIconsManifestReady');
        $readyProperty->setAccessible(true);
        $ensureMethod = $reflection->getMethod('ensureBladeIconsManifest');
        $ensureMethod->setAccessible(true);

        $manifestPath = app()->bootstrapPath('cache/blade-icons.php');
        $originalManifestExists = is_file($manifestPath);
        $originalManifestContents = $originalManifestExists ? file_get_contents($manifestPath) : null;

        if ($originalManifestExists) {
            unlink($manifestPath);
        }

        clearstatcache(true, $manifestPath);
        $readyProperty->setValue(null, false);

        Artisan::shouldReceive('call')->once()->with('icons:cache')->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('icons:cache failed');

        try {
            $ensureMethod->invoke($this);
            $this->fail('Expected ensureBladeIconsManifest() to throw when icons:cache fails.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('icons:cache failed', $exception->getMessage());
            $this->assertFalse($readyProperty->getValue());
        } finally {
            $readyProperty->setValue(null, false);

            if ($originalManifestExists) {
                file_put_contents($manifestPath, $originalManifestContents ?: '');
            } elseif (is_file($manifestPath)) {
                unlink($manifestPath);
            }

            clearstatcache(true, $manifestPath);
        }
    }
}

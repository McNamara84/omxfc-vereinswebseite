<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('ensure blade icons manifest throws when cache generation fails', function (): void {
    $reflection = new \ReflectionClass(TestCase::class);
    $readyProperty = $reflection->getProperty('bladeIconsManifestReady');
    $ensureMethod = $reflection->getMethod('ensureBladeIconsManifest');
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
        expect($exception->getMessage())->toContain('icons:cache failed')
            ->and($readyProperty->getValue())->toBeFalse();
    } finally {
        $readyProperty->setValue(null, false);
        if ($originalManifestExists) {
            file_put_contents($manifestPath, $originalManifestContents ?: '');
        } elseif (is_file($manifestPath)) {
            unlink($manifestPath);
        }
        clearstatcache(true, $manifestPath);
    }
});

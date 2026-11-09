<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;

trait AppServiceProviderTestFixtures
{
    use RefreshDatabase;

    private const TEST_VITE_BUILD_DIRECTORY = 'build-app-service-provider-test';

    private const TEST_VITE_ASSET = 'assets/app-service-provider-test.css';

    private const TEST_VITE_SCRIPT_ASSET = 'assets/app-service-provider-test.js';

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
        file_put_contents($manifestPath, json_encode(['resources/css/app.css' => ['file' => self::TEST_VITE_ASSET, 'src' => 'resources/css/app.css', 'isEntry' => true], 'resources/js/app.js' => ['file' => self::TEST_VITE_SCRIPT_ASSET, 'src' => 'resources/js/app.js', 'isEntry' => true]], JSON_THROW_ON_ERROR));
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

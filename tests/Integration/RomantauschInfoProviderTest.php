<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(RomantauschInfoProvider::class);

test('returns translations for configured locale', function (): void {
    $this->app['config']->set('romantausch.locale', 'de');
    $this->app['config']->set('romantausch.fallback_locale', 'de');
    $provider = new RomantauschInfoProvider;
    $info = $provider->getInfo();
    expect($info)->toBeArray()
        ->and($info['title'])->toBe(Lang::get('romantausch.info.title', [], 'de'));
});

test('falls back to configured fallback locale', function (): void {
    $this->app['config']->set('romantausch.locale', 'fr');
    $this->app['config']->set('romantausch.fallback_locale', 'de');
    $provider = new RomantauschInfoProvider;
    $info = $provider->getInfo();
    expect($info)->toBeArray()
        ->and($info['title'])->toBe(Lang::get('romantausch.info.title', [], 'de'));
});

test('uses application fallback when configuration missing', function (): void {
    $this->app['config']->set('romantausch.locale', 'fr');
    $this->app['config']->set('romantausch.fallback_locale', null);
    $this->app['config']->set('app.fallback_locale', 'de');
    $provider = new RomantauschInfoProvider;
    $info = $provider->getInfo();
    expect($info)->toBeArray()
        ->and($info['title'])->toBe(Lang::get('romantausch.info.title', [], 'de'));
});

test('returns empty info when both locales are missing', function (): void {
    config(['romantausch.locale' => 'fr', 'romantausch.fallback_locale' => 'en']);
    Lang::shouldReceive('get')->once()->with('romantausch.info', [], 'fr')->andReturn('romantausch.info');
    Lang::shouldReceive('get')->once()->with('romantausch.info', [], 'en')->andReturn('romantausch.info');
    $this->mock(RomantauschBaxxService::class, fn ($mock) => $mock->shouldNotReceive('getEffectiveRule'));
    expect(app(RomantauschInfoProvider::class)->getInfo())->toBe([]);
});

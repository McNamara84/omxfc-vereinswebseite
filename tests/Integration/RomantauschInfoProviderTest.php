<?php

namespace Tests\Integration;

use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RomantauschInfoProvider::class)]
class RomantauschInfoProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_translations_for_configured_locale(): void
    {
        $this->app['config']->set('romantausch.locale', 'de');
        $this->app['config']->set('romantausch.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider;

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }

    public function test_falls_back_to_configured_fallback_locale(): void
    {
        $this->app['config']->set('romantausch.locale', 'fr');
        $this->app['config']->set('romantausch.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider;

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }

    public function test_uses_application_fallback_when_configuration_missing(): void
    {
        $this->app['config']->set('romantausch.locale', 'fr');
        $this->app['config']->set('romantausch.fallback_locale', null);
        $this->app['config']->set('app.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider;

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }

    public function test_returns_empty_info_when_both_locales_are_missing(): void
    {
        config(['romantausch.locale' => 'fr', 'romantausch.fallback_locale' => 'en']);
        Lang::shouldReceive('get')->once()->with('romantausch.info', [], 'fr')->andReturn('romantausch.info');
        Lang::shouldReceive('get')->once()->with('romantausch.info', [], 'en')->andReturn('romantausch.info');
        $this->mock(RomantauschBaxxService::class, fn ($mock) => $mock->shouldNotReceive('getEffectiveRule'));

        $this->assertSame([], app(RomantauschInfoProvider::class)->getInfo());
    }
}

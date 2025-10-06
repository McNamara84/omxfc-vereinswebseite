<?php

namespace Tests\Unit;

use App\Services\RomantauschInfoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class RomantauschInfoProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_translations_for_configured_locale(): void
    {
        $this->app['config']->set('romantausch.locale', 'de');
        $this->app['config']->set('romantausch.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider();

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }

    public function test_falls_back_to_configured_fallback_locale(): void
    {
        $this->app['config']->set('romantausch.locale', 'fr');
        $this->app['config']->set('romantausch.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider();

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }

    public function test_uses_application_fallback_when_configuration_missing(): void
    {
        $this->app['config']->set('romantausch.locale', 'fr');
        $this->app['config']->set('romantausch.fallback_locale', null);
        $this->app['config']->set('app.fallback_locale', 'de');

        $provider = new RomantauschInfoProvider();

        $info = $provider->getInfo();

        $this->assertIsArray($info);
        $this->assertSame(Lang::get('romantausch.info.title', [], 'de'), $info['title']);
    }
}

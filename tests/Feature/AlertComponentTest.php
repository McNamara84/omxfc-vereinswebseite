<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;

class AlertComponentTest extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_alert_renders_title_slot_and_icon_without_description(): void
    {
        $html = Blade::render('<x-alert title="Karte noch nicht verfügbar" icon="o-lock-closed">Freischaltung erst nach der ersten erledigten Aufgabe.</x-alert>');

        $this->assertStringContainsString('Karte noch nicht verfügbar', $html);
        $this->assertStringContainsString('Freischaltung erst nach der ersten erledigten Aufgabe.', $html);
        $this->assertStringContainsString('font-bold', $html);
        $this->assertTrue(
            str_contains($html, 'data-icon-name="o-lock-closed"')
                || str_contains($html, 'data-slot="icon"'),
            'Expected the alert to render icon markup.',
        );
        $this->assertStringNotContainsString('•', $html);
    }

    public function test_alert_renders_description_and_slot_together(): void
    {
        $html = Blade::render('<x-alert title="Hinweis" description="Kurzbeschreibung">Zusätzlicher Hinweistext.</x-alert>');

        $this->assertStringContainsString('Hinweis', $html);
        $this->assertStringContainsString('Kurzbeschreibung', $html);
        $this->assertStringContainsString('Zusätzlicher Hinweistext.', $html);
    }

    public function test_alert_does_not_duplicate_passed_attributes(): void
    {
        $html = Blade::render('<x-alert id="warnung" data-testid="alert-komponente">Testinhalt</x-alert>');

        $this->assertSame(1, substr_count($html, 'id="warnung"'));
        $this->assertSame(1, substr_count($html, 'data-testid="alert-komponente"'));
    }
}
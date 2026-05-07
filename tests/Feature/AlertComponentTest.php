<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AlertComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_renders_title_slot_and_icon_without_description(): void
    {
        $html = Blade::render('<x-alert title="Karte noch nicht verfügbar" icon="o-lock-closed">Freischaltung erst nach der ersten erledigten Aufgabe.</x-alert>');

        $this->assertStringContainsString('Karte noch nicht verfügbar', $html);
        $this->assertStringContainsString('Freischaltung erst nach der ersten erledigten Aufgabe.', $html);
        $this->assertStringContainsString('font-bold', $html);
        $this->assertStringContainsString('data-icon-name="o-lock-closed"', $html);
        $this->assertStringNotContainsString('•', $html);
    }

    public function test_alert_renders_description_and_slot_together(): void
    {
        $html = Blade::render('<x-alert title="Hinweis" description="Kurzbeschreibung">Zusätzlicher Hinweistext.</x-alert>');

        $this->assertStringContainsString('Hinweis', $html);
        $this->assertStringContainsString('Kurzbeschreibung', $html);
        $this->assertStringContainsString('Zusätzlicher Hinweistext.', $html);
    }
}
<?php

namespace Tests\Feature;

use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Symfony\Component\DomCrawler\Crawler;

class UiIconActionTest extends BaseTestCase
{
    public function createApplication()
    {
        ArtisanApplication::forgetBootstrappers();

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_it_renders_an_accessible_icon_button_with_default_tooltip_alignment(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.icon-action
                icon="o-trash"
                tooltip="Eintrag löschen"
                class="btn-error"
                wire:click="remove"
                data-testid="remove-action"
            />
        BLADE);

        $crawler = new Crawler($html);
        $tooltip = $crawler->filter('span.tooltip.tooltip-top.tooltip-center[data-tip="Eintrag löschen"]');
        $button = $tooltip->filter('button[data-testid="remove-action"]');

        $this->assertCount(1, $tooltip);
        $this->assertCount(1, $button);
        $this->assertSame('Eintrag löschen', $button->attr('aria-label'));
        $this->assertSame('remove', $button->attr('wire:click'));
        $this->assertStringContainsString('btn-error', $button->attr('class') ?? '');
    }

    public function test_it_supports_edge_alignment_and_wrapper_sizing(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.icon-action
                icon="o-information-circle"
                tooltip="Weitere Informationen"
                tooltip-position="bottom"
                tooltip-align="end"
                wrapper-class="w-full"
            />
        BLADE);

        $crawler = new Crawler($html);
        $tooltip = $crawler->filter('span.tooltip.tooltip-bottom.tooltip-end.w-full');

        $this->assertCount(1, $tooltip);
        $this->assertSame('Weitere Informationen', $tooltip->attr('data-tip'));
        $this->assertSame('Weitere Informationen', $tooltip->filter('button')->attr('aria-label'));
    }

    public function test_it_rejects_unknown_tooltip_positions(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unsupported tooltip position: diagonal');

        Blade::render(<<<'BLADE'
            <x-ui.icon-action icon="o-x-mark" tooltip="Schließen" tooltip-position="diagonal" />
        BLADE);
    }

    public function test_it_rejects_unknown_tooltip_alignments(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Unsupported tooltip alignment: outside');

        Blade::render(<<<'BLADE'
            <x-ui.icon-action icon="o-x-mark" tooltip="Schließen" tooltip-align="outside" />
        BLADE);
    }
}

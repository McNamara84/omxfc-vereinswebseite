<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Symfony\Component\DomCrawler\Crawler;

pest()->extend(BaseTestCase::class)->use(UiIconActionTestFixtures::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
});

test('it renders an accessible icon button with default tooltip alignment', function (): void {
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
    expect($tooltip)->toHaveCount(1)
        ->and($button)->toHaveCount(1)
        ->and($button->attr('aria-label'))->toBe('Eintrag löschen')
        ->and($button->attr('wire:click'))->toBe('remove')
        ->and($button->attr('class') ?? '')->toContain('btn-error');
});

test('it supports edge alignment and wrapper sizing', function (): void {
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
    expect($tooltip)->toHaveCount(1)
        ->and($tooltip->attr('data-tip'))->toBe('Weitere Informationen')
        ->and($tooltip->filter('button')->attr('aria-label'))->toBe('Weitere Informationen');
});

test('it rejects unknown tooltip positions', function (): void {
    expect(fn () => Blade::render(<<<'BLADE'
        <x-ui.icon-action icon="o-x-mark" tooltip="Schließen" tooltip-position="diagonal" />
    BLADE))->toThrow(ViewException::class, 'Unsupported tooltip position: diagonal');
});

test('it rejects unknown tooltip alignments', function (): void {
    expect(fn () => Blade::render(<<<'BLADE'
        <x-ui.icon-action icon="o-x-mark" tooltip="Schließen" tooltip-align="outside" />
    BLADE))->toThrow(ViewException::class, 'Unsupported tooltip alignment: outside');
});

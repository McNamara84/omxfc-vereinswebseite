<?php

declare(strict_types=1);

test('zeigt die startseite im light mode', function (): void {
    visit('/', ['waitUntil' => 'domcontentloaded'])
        ->inLightMode()
        ->assertPathIs('/')
        ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
        ->assertVisible('[data-testid="theme-toggle"]')
        ->assertAttribute('[data-testid="theme-toggle"]', 'aria-pressed', 'false')
        ->assertScript('document.documentElement.dataset.theme', 'caramellatte')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoJavaScriptErrors();
});

test('schaltet die startseite ueber den header-toggle in den dark mode', function (): void {
    $page = visit('/', ['waitUntil' => 'domcontentloaded'])
        ->inLightMode()
        ->assertPathIs('/')
        ->assertVisible('[data-testid="theme-toggle"]')
        ->assertAttribute('[data-testid="theme-toggle"]', 'aria-pressed', 'false')
        ->assertScript('document.documentElement.dataset.theme', 'caramellatte')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->click('[data-testid="theme-toggle"]')
        ->assertAttribute('[data-testid="theme-toggle"]', 'aria-pressed', 'true')
        ->assertScript('document.documentElement.dataset.theme', 'coffee')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('window.localStorage.getItem("mary-theme")', '"coffee"')
        ->assertScript('window.localStorage.getItem("mary-class")', '"dark"')
        ->assertNoJavaScriptErrors();

    $page->script('window.location.reload()');
    $page->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');

    $page->assertScript('document.documentElement.dataset.theme', 'coffee')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertAttribute('[data-testid="theme-toggle"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

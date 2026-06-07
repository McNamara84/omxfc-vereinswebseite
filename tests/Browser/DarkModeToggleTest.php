<?php

it('zeigt die startseite im light mode', function () {
    visit('/', ['waitUntil' => 'domcontentloaded'])
        ->inLightMode()
        ->assertPathIs('/')
        ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
        ->assertVisible('@theme-toggle')
        ->assertScript('document.documentElement.dataset.theme', 'caramellatte')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoJavaScriptErrors();
});

it('schaltet die startseite ueber den header-toggle in den dark mode', function () {
    $page = visit('/', ['waitUntil' => 'domcontentloaded'])
        ->inLightMode()
        ->assertPathIs('/')
        ->assertVisible('@theme-toggle')
        ->assertScript('document.documentElement.dataset.theme', 'caramellatte')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->click('@theme-toggle')
        ->assertScript('document.documentElement.dataset.theme', 'coffee')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('window.localStorage.getItem("mary-theme")', '"coffee"')
        ->assertScript('window.localStorage.getItem("mary-class")', '"dark"')
        ->assertNoJavaScriptErrors();

    $page->script('() => window.location.reload()');
    $page->waitForEvent('domcontentloaded');

    $page->assertScript('document.documentElement.dataset.theme', 'coffee')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoJavaScriptErrors();
});

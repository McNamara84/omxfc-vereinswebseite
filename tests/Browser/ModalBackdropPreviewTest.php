<?php

it('zeigt für Vorschau-Modals deckende Backdrops', function () {
    $page = visit('/_testing/modal-vorschau', ['waitUntil' => 'domcontentloaded']);

    $page->assertTitle('Modal-Vorschau')
        ->assertScript('window.__omxfcPreviewExpectedModalCount() > 0', true)
        ->assertScript('window.__omxfcPreviewExpectedModalCount() === document.querySelectorAll("[data-modal-trigger]").length', true)
        ->assertNoJavaScriptErrors();

    $modalIds = $page->script("() => Array.from(document.querySelectorAll('[data-modal-trigger]')).map((trigger) => trigger.dataset.modalTarget)");
    $expectedModalCount = $page->script('() => window.__omxfcPreviewExpectedModalCount()');

    expect($modalIds)->toHaveCount($expectedModalCount);

    foreach ($modalIds as $modalId) {
        $page->click("#open-{$modalId}")
            ->assertVisible("#{$modalId}")
            ->assertScript("window.__omxfcPreviewBackdropAlpha('{$modalId}') > 0.2", true);

        $page->script('window.__omxfcClosePreviewModals()');
    }
});
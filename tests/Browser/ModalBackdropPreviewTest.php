<?php

it('zeigt fuer Vorschau-Modals deckende Backdrops', function () {
    $page = visit('/_testing/modal-vorschau');

    $page->assertSee('Modal-Vorschau')
        ->assertScript('window.__omxfcPreviewExpectedModalCount() > 0', true)
        ->assertScript('window.__omxfcPreviewExpectedModalCount() === document.querySelectorAll("[data-modal-trigger]").length', true)
        ->assertNoJavaScriptErrors();

    $page->click('#open-preview-todo-delete')
        ->assertVisible('#preview-todo-delete')
        ->assertScript('window.__omxfcPreviewBackdropAlpha("preview-todo-delete") > 0.2', true);

    $page->script('window.__omxfcClosePreviewModals()');

    $page->click('#open-preview-profile-photo')
        ->assertVisible('#preview-profile-photo')
        ->assertScript('window.__omxfcPreviewBackdropAlpha("preview-profile-photo") > 0.2', true);

    $page->script('window.__omxfcClosePreviewModals()');

    $page->click('#open-preview-chronik-lightbox')
        ->assertVisible('#preview-chronik-lightbox')
        ->assertScript('window.__omxfcPreviewBackdropAlpha("preview-chronik-lightbox") > 0.2', true);
});
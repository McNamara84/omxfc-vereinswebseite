import { vi } from 'vitest';

import { initMarkdownEditors, registerMarkdownEditorLifecycle } from '@/reviews/markdown-editor.js';

function renderEditor(value = '') {
    document.body.innerHTML = `
        <div data-markdown-editor>
            <button type="button" data-markdown-action="bold">Fett</button>
            <button type="button" data-markdown-action="italic">Kursiv</button>
            <button type="button" data-markdown-action="bullet-list">Bullet-Liste</button>
            <button type="button" data-markdown-action="numbered-list">Nummerierte Liste</button>
            <button type="button" data-markdown-action="link">Link</button>
            <textarea data-markdown-input>${value}</textarea>
        </div>
    `;

    const root = document.querySelector('[data-markdown-editor]');
    const textarea = root.querySelector('[data-markdown-input]');

    textarea.value = value;

    return { root, textarea };
}

function clickAction(root, action) {
    root.querySelector(`[data-markdown-action="${action}"]`).click();
}

describe('review markdown editor', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        delete document.documentElement.dataset.reviewMarkdownEditorLifecycleRegistered;
        vi.restoreAllMocks();
    });

    it('umschliesst die Auswahl fuer Fett und dispatcht Livewire-relevante Events', () => {
        const { root, textarea } = renderEditor('Alpha Beta Gamma');
        const inputSpy = vi.fn();
        const changeSpy = vi.fn();

        textarea.setSelectionRange(6, 10);
        textarea.addEventListener('input', inputSpy);
        textarea.addEventListener('change', changeSpy);

        initMarkdownEditors();
        clickAction(root, 'bold');

        expect(textarea.value).toBe('Alpha **Beta** Gamma');
        expect(textarea.selectionStart).toBe(8);
        expect(textarea.selectionEnd).toBe(12);
        expect(inputSpy).toHaveBeenCalledTimes(1);
        expect(changeSpy).toHaveBeenCalledTimes(1);
    });

    it('fuegt kursiven Platzhalter bei leerer Auswahl ein', () => {
        const { root, textarea } = renderEditor('Alpha');

        textarea.setSelectionRange(5, 5);

        initMarkdownEditors();
        clickAction(root, 'italic');

        expect(textarea.value).toBe('Alpha*Text*');
        expect(textarea.selectionStart).toBe(6);
        expect(textarea.selectionEnd).toBe(10);
    });

    it('praefixiert mehrere Zeilen als Bullet-Liste', () => {
        const content = 'Erster Punkt\nZweiter Punkt';
        const { root, textarea } = renderEditor(content);

        textarea.setSelectionRange(0, content.length);

        initMarkdownEditors();
        clickAction(root, 'bullet-list');

        expect(textarea.value).toBe('- Erster Punkt\n- Zweiter Punkt');
    });

    it('nummeriert mehrere Zeilen fortlaufend', () => {
        const content = 'Erster Schritt\nZweiter Schritt\nDritter Schritt';
        const { root, textarea } = renderEditor(content);

        textarea.setSelectionRange(0, content.length);

        initMarkdownEditors();
        clickAction(root, 'numbered-list');

        expect(textarea.value).toBe('1. Erster Schritt\n2. Zweiter Schritt\n3. Dritter Schritt');
    });

    it('setzt Hyperlinks ueber den Prompt-Wert', () => {
        const { root, textarea } = renderEditor('Mehr Infos');

        textarea.setSelectionRange(0, 10);
        vi.spyOn(window, 'prompt').mockReturnValue('https://example.com/review');

        initMarkdownEditors();
        clickAction(root, 'link');

        expect(textarea.value).toBe('[Mehr Infos](https://example.com/review)');
    });

    it('bleibt bei mehrfacher Initialisierung idempotent', () => {
        const { root, textarea } = renderEditor('Alpha');

        textarea.setSelectionRange(0, 5);

        initMarkdownEditors();
        initMarkdownEditors();
        clickAction(root, 'bold');

        expect(textarea.value).toBe('**Alpha**');
    });

    it('initialisiert spaeter hinzugefuegte Editoren nach livewire:navigated', () => {
        const first = renderEditor('Erster');

        registerMarkdownEditorLifecycle(document);
        first.textarea.setSelectionRange(0, 6);
        clickAction(first.root, 'bold');
        expect(first.textarea.value).toBe('**Erster**');

        document.body.innerHTML += `
            <div data-markdown-editor>
                <button type="button" data-markdown-action="bold">Fett</button>
                <button type="button" data-markdown-action="italic">Kursiv</button>
                <button type="button" data-markdown-action="bullet-list">Bullet-Liste</button>
                <button type="button" data-markdown-action="numbered-list">Nummerierte Liste</button>
                <button type="button" data-markdown-action="link">Link</button>
                <textarea data-markdown-input>Zweiter</textarea>
            </div>
        `;

        const secondRoot = document.querySelectorAll('[data-markdown-editor]')[1];
        const secondTextarea = secondRoot.querySelector('[data-markdown-input]');
        secondTextarea.setSelectionRange(0, 7);

        document.dispatchEvent(new Event('livewire:navigated'));
        clickAction(secondRoot, 'bold');

        expect(secondTextarea.value).toBe('**Zweiter**');
    });
});
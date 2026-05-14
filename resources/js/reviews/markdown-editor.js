const EDITOR_SELECTOR = '[data-markdown-editor]';
const INPUT_SELECTOR = '[data-markdown-input]';
const ACTION_SELECTOR = '[data-markdown-action]';
const INITIALIZED_FLAG = 'markdownEditorInitialized';
const LIFECYCLE_FLAG = 'reviewMarkdownEditorLifecycleRegistered';
const lifecycleRegistrations = new WeakMap();

function focusTextarea(textarea) {
    textarea.focus();
}

function syncTextarea(textarea) {
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
}

function replaceSelection(textarea, replacement, nextSelectionStart, nextSelectionEnd) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;

    textarea.setRangeText(replacement, start, end, 'end');
    textarea.setSelectionRange(nextSelectionStart, nextSelectionEnd);

    focusTextarea(textarea);
    syncTextarea(textarea);
}

function wrapSelection(textarea, marker, placeholder) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const selectedText = textarea.value.slice(start, end);
    const content = selectedText || placeholder;
    const replacement = `${marker}${content}${marker}`;
    const selectionStart = start + marker.length;
    const selectionEnd = selectionStart + content.length;

    replaceSelection(textarea, replacement, selectionStart, selectionEnd);
}

function getLineRange(textarea) {
    const value = textarea.value;
    const selectionStart = textarea.selectionStart ?? 0;
    const selectionEnd = textarea.selectionEnd ?? selectionStart;
    const blockStart = value.lastIndexOf('\n', Math.max(selectionStart - 1, 0)) + 1;
    const nextNewline = value.indexOf('\n', selectionEnd);
    const blockEnd = nextNewline === -1 ? value.length : nextNewline;

    return {
        blockStart,
        blockEnd,
        block: value.slice(blockStart, blockEnd),
    };
}

function prefixLines(textarea, prefixBuilder) {
    const { blockStart, blockEnd, block } = getLineRange(textarea);
    const replacement = block
        .split('\n')
        .map((line, index) => `${prefixBuilder(index)}${line}`)
        .join('\n');

    textarea.setRangeText(replacement, blockStart, blockEnd, 'select');
    textarea.setSelectionRange(blockStart, blockStart + replacement.length);

    focusTextarea(textarea);
    syncTextarea(textarea);
}

function createLink(textarea) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const selectedText = textarea.value.slice(start, end);
    const linkText = selectedText || 'Linktext';
    const url = window.prompt('URL für den Hyperlink eingeben', 'https://');

    if (url === null) {
        focusTextarea(textarea);

        return;
    }

    const trimmedUrl = url.trim();

    if (trimmedUrl === '') {
        focusTextarea(textarea);

        return;
    }

    const replacement = `[${linkText}](${trimmedUrl})`;
    replaceSelection(textarea, replacement, start, start + replacement.length);
}

export function applyMarkdownAction(textarea, action) {
    switch (action) {
        case 'bold':
            wrapSelection(textarea, '**', 'Text');
            break;
        case 'italic':
            wrapSelection(textarea, '*', 'Text');
            break;
        case 'bullet-list':
            prefixLines(textarea, () => '- ');
            break;
        case 'numbered-list':
            prefixLines(textarea, (index) => `${index + 1}. `);
            break;
        case 'link':
            createLink(textarea);
            break;
        default:
            break;
    }
}

function bindActionButton(button, textarea) {
    button.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });

    button.addEventListener('click', () => {
        applyMarkdownAction(textarea, button.dataset.markdownAction);
    });
}

function getEditorRoots(root = document) {
    if (!(root instanceof Element || root instanceof Document)) {
        return [];
    }

    const roots = [];

    if (root instanceof Element && root.matches(EDITOR_SELECTOR)) {
        roots.push(root);
    }

    roots.push(...root.querySelectorAll(EDITOR_SELECTOR));

    return roots;
}

export function initMarkdownEditors(root = document) {
    for (const editorRoot of getEditorRoots(root)) {
        if (editorRoot.dataset[INITIALIZED_FLAG] === 'true') {
            continue;
        }

        const textarea = editorRoot.querySelector(INPUT_SELECTOR);

        if (!(textarea instanceof HTMLTextAreaElement)) {
            continue;
        }

        editorRoot.dataset[INITIALIZED_FLAG] = 'true';

        for (const button of editorRoot.querySelectorAll(ACTION_SELECTOR)) {
            bindActionButton(button, textarea);
        }
    }
}

export function registerMarkdownEditorLifecycle(doc = document) {
    const existingRegistration = lifecycleRegistrations.get(doc);

    if (existingRegistration) {
        existingRegistration.initializeEditors();

        return existingRegistration.cleanup;
    }

    const root = doc.documentElement;

    if (root) {
        root.dataset[LIFECYCLE_FLAG] = 'true';
    }

    const initializeEditors = () => initMarkdownEditors(doc);
    let domContentLoadedHandler = null;

    if (doc.readyState === 'loading') {
        domContentLoadedHandler = () => {
            initializeEditors();

            if (domContentLoadedHandler !== null) {
                doc.removeEventListener('DOMContentLoaded', domContentLoadedHandler);
                domContentLoadedHandler = null;
            }
        };

        doc.addEventListener('DOMContentLoaded', domContentLoadedHandler);
    } else {
        initializeEditors();
    }

    doc.addEventListener('livewire:navigated', initializeEditors);

    const cleanup = () => {
        doc.removeEventListener('livewire:navigated', initializeEditors);

        if (domContentLoadedHandler !== null) {
            doc.removeEventListener('DOMContentLoaded', domContentLoadedHandler);
            domContentLoadedHandler = null;
        }

        lifecycleRegistrations.delete(doc);

        if (root) {
            delete root.dataset[LIFECYCLE_FLAG];
        }
    };

    lifecycleRegistrations.set(doc, {
        initializeEditors,
        cleanup,
    });

    return cleanup;
}
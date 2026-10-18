import { beforeAll, beforeEach, afterEach, describe, expect, it, vi } from 'vitest';

let ux;
const hooks = new Map();
beforeAll(async () => {
    window.Livewire = { hook: vi.fn((name, callback) => hooks.set(name, callback)) };
    ux = await import('../../resources/js/ux.js');
});
beforeEach(() => {
    document.body.innerHTML = '<form wire:submit="submit" data-ux-form data-guard-dirty><input id="title" name="title" value="Anfang"><input type="hidden" name="_token" value="secret"><textarea name="content">Text</textarea><input type="checkbox" name="accept"></form>';
    document.dispatchEvent(new Event('livewire:navigated'));
    vi.spyOn(window, 'confirm').mockReturnValue(false);
});
afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = ''; });
const navigate = () => {
    const event = new CustomEvent('livewire:navigate', { cancelable: true });
    document.dispatchEvent(event);
    return event;
};

describe('page destinations', () => {
    it('opens all ancestor details and focuses an anchor target', () => {
        document.body.innerHTML = '<details><summary>A</summary><details id="target"><summary>B</summary><p>Inhalt</p></details></details>';
        expect(ux.revealHashTarget('#target')).toBe(true);
        expect([...document.querySelectorAll('details')].every(element => element.open)).toBe(true);
        expect(document.activeElement.id).toBe('target');
    });
    it.each(['#missing', '#%E0%A4%A', ''])('ignores absent or malformed targets: %s', hash => {
        expect(ux.revealHashTarget(hash)).toBe(false);
    });
    it('reveals an anchor when the location hash changes', () => {
        document.body.innerHTML = '<details id="hash-target"><summary>Ziel</summary></details>';
        history.replaceState(null, '', '#hash-target');
        window.dispatchEvent(new Event('hashchange'));
        expect(document.querySelector('details').open).toBe(true);
        expect(document.activeElement.id).toBe('hash-target');
        history.replaceState(null, '', location.pathname);
    });
    it('reveals a deep link after Livewire navigation renders its target', () => {
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => { callback(); return 1; });
        document.body.innerHTML = '<details id="deep-link"><summary>Ziel</summary></details>';
        history.replaceState(null, '', '#deep-link');
        document.dispatchEvent(new Event('livewire:navigated'));
        expect(document.querySelector('details').open).toBe(true);
        expect(document.activeElement.id).toBe('deep-link');
        history.replaceState(null, '', location.pathname);
    });
});

describe('unsaved reviews', () => {
    it('allows clean navigation without asking', () => {
        expect(navigate().defaultPrevented).toBe(false);
        expect(window.confirm).not.toHaveBeenCalled();
    });
    it('keeps changed inputs when navigation is cancelled', () => {
        document.querySelector('#title').value = 'Ungespeichert';
        expect(navigate().defaultPrevented).toBe(true);
        expect(document.querySelector('#title').value).toBe('Ungespeichert');
    });
    it('allows confirmed navigation', () => {
        document.querySelector('textarea').value += '!';
        window.confirm.mockReturnValue(true);
        expect(navigate().defaultPrevented).toBe(false);
    });
    it('detects checkbox changes and ignores hidden CSRF tokens', () => {
        const form = document.querySelector('form');
        const clean = ux.formSnapshot(form);
        form.querySelector('[name=_token]').value = 'new';
        expect(ux.formSnapshot(form)).toBe(clean);
        form.querySelector('[type=checkbox]').checked = true;
        expect(ux.formSnapshot(form)).not.toBe(clean);
    });
    it('does not warn when a changed value is restored', () => {
        const title = document.querySelector('#title');
        title.value = 'Anders'; title.value = 'Anfang';
        expect(navigate().defaultPrevented).toBe(false);
    });
    it('warns before unloading a dirty page', () => {
        document.querySelector('#title').value = 'Änderung';
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);
    });
    it('permits the redirect after submitting and re-enables the guard after an error', () => {
        const form = document.querySelector('form');
        form.querySelector('#title').value = 'Änderung';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(navigate().defaultPrevented).toBe(false);
        hooks.get('request')({ fail: callback => callback() });
        expect(navigate().defaultPrevented).toBe(true);
    });
    it('installs lifecycle listeners only once', () => {
        ux.registerUxLifecycle(); ux.registerUxLifecycle();
        document.querySelector('#title').value = 'Änderung';
        navigate();
        expect(window.confirm).toHaveBeenCalledTimes(1);
    });
    it('does not reset an in-flight review when an unrelated component morphs', () => {
        const form = document.querySelector('form');
        form.querySelector('#title').value = 'Änderung';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        hooks.get('morphed')({ el: document.createElement('div') });
        expect(navigate().defaultPrevented).toBe(false);
    });
    it('restores the leave warning after validation morphs the enclosing component', () => {
        const form = document.querySelector('form');
        form.querySelector('#title').value = 'Noch nicht gespeichert';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(navigate().defaultPrevented).toBe(false);
        hooks.get('morphed')({ el: document.body });
        expect(navigate().defaultPrevented).toBe(true);
    });
    it('does not register duplicate Livewire hooks when initialization fires late', () => {
        const calls = window.Livewire.hook.mock.calls.length;
        document.dispatchEvent(new Event('livewire:init'));
        expect(window.Livewire.hook.mock.calls.length).toBe(calls);
    });
    it('uses new form baselines after navigation without warning', () => {
        document.querySelector('#title').value = 'Geladener Entwurf';
        const newForm = document.querySelector('form').cloneNode(true);
        document.querySelector('form').replaceWith(newForm);
        document.dispatchEvent(new Event('livewire:navigated'));
        expect(navigate().defaultPrevented).toBe(false);
    });
});

describe('form error focus', () => {
    const commit = (root, calls = [{ method: 'submit' }]) => {
        let success;
        hooks.get('commit')({ component: { el: root }, commit: { calls }, succeed: callback => { success = callback; } });
        return () => success();
    };
    it('focuses a summary only after a submission, once per submission', () => {
        const form = document.querySelector('form');
        form.insertAdjacentHTML('beforeend', '<div data-ux-error-summary tabindex="-1">Fehler</div>');
        const summary = form.querySelector('[data-ux-error-summary]');
        ux.focusFormErrors();
        expect(document.activeElement).not.toBe(summary);
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        commit(form)();
        expect(document.activeElement).toBe(summary);
        form.querySelector('#title').focus();
        ux.focusFormErrors();
        expect(document.activeElement.id).toBe('title');
    });
    it('does not move focus after a successful form without errors', () => {
        const form = document.querySelector('form');
        const field = form.querySelector('#title'); field.focus();
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        commit(form)();
        expect(document.activeElement).toBe(field);
    });
    it('waits for the submit response when an earlier field update morphs the form', () => {
        const form = document.querySelector('form');
        const finishFieldUpdate = commit(form, []);
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        const finishSubmission = commit(form);
        hooks.get('morphed')({ el: form });
        finishFieldUpdate();
        form.insertAdjacentHTML('beforeend', '<div data-ux-error-summary tabindex="-1">Fehler</div>');
        const summary = form.querySelector('[data-ux-error-summary]');
        finishSubmission();
        expect(document.activeElement).toBe(summary);
    });
    it('ignores unrelated method responses and focuses a nested review form after save', () => {
        const form = document.querySelector('form');
        form.setAttribute('wire:submit', 'save()');
        form.insertAdjacentHTML('beforeend', '<div data-ux-error-summary tabindex="-1">Fehler</div>');
        const summary = form.querySelector('[data-ux-error-summary]');
        const wrapper = document.createElement('div');
        form.replaceWith(wrapper);
        wrapper.append(form);
        commit(wrapper, [{ method: 'save' }])();
        expect(document.activeElement).not.toBe(summary);
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        commit(wrapper, [{ method: 'preview' }])();
        expect(document.activeElement).not.toBe(summary);
        commit(wrapper, [{ method: 'save' }])();
        expect(document.activeElement).toBe(summary);
    });
});

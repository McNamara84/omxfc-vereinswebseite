import { describe, expect, test, beforeEach } from '@jest/globals';
import {
    applyFilterState,
    getFilterMessage,
    initTodoFilters,
    normaliseFilter,
    updateButtons,
    updateSections,
} from '../../resources/js/utils/todoFilters';

describe('todoFilters utilities', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    test('normaliseFilter falls back to all for unknown values', () => {
        expect(normaliseFilter('unknown')).toBe('all');
        expect(normaliseFilter(' Pending ')).toBe('pending');
        expect(normaliseFilter()).toBe('all');
    });

    test('getFilterMessage returns descriptive German text', () => {
        expect(getFilterMessage('all')).toContain('alle verfügbaren');
        expect(getFilterMessage('open')).toContain('offene Challenges');
    });

    test('updateButtons toggles aria-pressed and data-active', () => {
        document.body.innerHTML = `
            <form>
                <button data-todo-filter data-filter="all"></button>
                <button data-todo-filter data-filter="open"></button>
            </form>
        `;

        const buttons = document.querySelectorAll('[data-todo-filter]');

        updateButtons(buttons, 'open');

        expect(buttons[0].getAttribute('aria-pressed')).toBe('false');
        expect(buttons[0].dataset.active).toBe('false');
        expect(buttons[1].getAttribute('aria-pressed')).toBe('true');
        expect(buttons[1].dataset.active).toBe('true');
    });

    test('updateSections hides non matching sections', () => {
        document.body.innerHTML = `
            <section data-todo-section="assigned"></section>
            <section data-todo-section="open"></section>
            <section data-todo-section="pending"></section>
        `;

        const sections = document.querySelectorAll('[data-todo-section]');

        updateSections(sections, 'open');

        expect(sections[0].classList.contains('hidden')).toBe(true);
        expect(sections[0].getAttribute('aria-hidden')).toBe('true');
        expect(sections[1].classList.contains('hidden')).toBe(false);
        expect(sections[1].getAttribute('aria-hidden')).toBe('false');
        expect(sections[2].classList.contains('hidden')).toBe(true);
    });

    test('initTodoFilters wires listeners and updates status message', () => {
        document.body.innerHTML = `
            <section data-todo-filter-wrapper>
                <div>
                    <p data-todo-filter-status></p>
                    <button type="button" data-todo-filter-toggle aria-expanded="false"
                        aria-controls="todo-panel" data-label-open="Filter anzeigen"
                        data-label-close="Filter verbergen">
                        <span data-todo-filter-toggle-text>Filter anzeigen</span>
                    </button>
                </div>
                <div id="todo-panel" data-todo-filter-panel>
                    <form data-todo-filter-form data-current-filter="all">
                        <div>
                            <button type="button" data-todo-filter data-filter="assigned" data-active="false"></button>
                            <button type="button" data-todo-filter data-filter="open" data-active="false"></button>
                            <button type="submit" data-todo-filter data-filter="pending" data-active="false"></button>
                        </div>
                    </form>
                </div>
                <div data-todo-section="assigned"></div>
                <div data-todo-section="open"></div>
                <div data-todo-section="pending"></div>
            </section>
        `;

        const statusElement = document.querySelector('[data-todo-filter-status]');

        initTodoFilters(document);

        expect(statusElement.textContent).toContain('alle verfügbaren Challenges');

        const assignedButton = document.querySelector('[data-filter="assigned"]');
        assignedButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(statusElement.textContent).toContain('übernommenen Challenges');

        const hiddenAssigned = document.querySelector('[data-todo-section="open"]');
        expect(hiddenAssigned.classList.contains('hidden')).toBe(true);

        const pendingButton = document.querySelector('[data-filter="pending"]');
        pendingButton.dispatchEvent(new Event('click', { bubbles: true }));
        expect(statusElement.textContent).toContain('Verifizierung warten');
    });

    test('initTodoFilters toggles the disclosure panel and focuses the first control', () => {
        document.body.innerHTML = `
            <section data-todo-filter-wrapper>
                <button type="button" data-todo-filter-toggle aria-expanded="false" aria-controls="filters"
                    data-label-open="Filter anzeigen" data-label-close="Filter verbergen">
                    <span data-todo-filter-toggle-text>Filter anzeigen</span>
                </button>
                <div id="filters" data-todo-filter-panel>
                    <form data-todo-filter-form data-current-filter="all">
                        <div>
                            <button type="button" data-todo-filter data-filter="all" data-active="true"></button>
                            <button type="button" data-todo-filter data-filter="open" data-active="false"></button>
                        </div>
                    </form>
                </div>
            </section>
        `;

        const toggle = document.querySelector('[data-todo-filter-toggle]');
        const panel = document.querySelector('[data-todo-filter-panel]');
        const firstButton = panel.querySelector('[data-filter="all"]');

        initTodoFilters(document);

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(panel.getAttribute('aria-hidden')).toBe('true');
        expect(toggle.textContent).toContain('Filter anzeigen');

        toggle.dispatchEvent(new Event('click'));

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(panel.hasAttribute('hidden')).toBe(false);
        expect(panel.getAttribute('aria-hidden')).toBe('false');
        expect(toggle.textContent).toContain('Filter verbergen');
        expect(document.activeElement).toBe(firstButton);

        toggle.dispatchEvent(new Event('click'));

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(toggle.textContent).toContain('Filter anzeigen');
    });

    test('applyFilterState returns the active filter', () => {
        document.body.innerHTML = `
            <section data-todo-section="assigned"></section>
            <section data-todo-section="open"></section>
        `;

        const buttons = [];
        const sections = document.querySelectorAll('[data-todo-section]');
        const statusElement = document.createElement('p');

        const result = applyFilterState({
            buttons,
            sections,
            statusElement,
            filter: 'assigned',
        });

        expect(result).toBe('assigned');
        expect(statusElement.textContent).toContain('übernommenen Challenges');
        expect(sections[0].classList.contains('hidden')).toBe(false);
        expect(sections[1].classList.contains('hidden')).toBe(true);
    });

    test('applyFilterState normalises invalid filters to all', () => {
        document.body.innerHTML = `
            <section data-todo-section="assigned"></section>
        `;

        const result = applyFilterState({
            buttons: [],
            sections: document.querySelectorAll('[data-todo-section]'),
            statusElement: null,
            filter: '  UNKNOWN  ',
        });

        expect(result).toBe('all');
    });

    test('updateButtons ignores non-HTMLElement entries gracefully', () => {
        document.body.innerHTML = `
            <button data-todo-filter data-filter="all"></button>
        `;

        const buttons = [
            document.querySelector('[data-todo-filter]'),
            null,
            {},
        ];

        updateButtons(buttons, 'all');

        expect(buttons[0].getAttribute('aria-pressed')).toBe('true');
    });

    test('initTodoFilters updates data attribute and leaves untouched when form missing', () => {
        initTodoFilters({});

        document.body.innerHTML = `
            <section data-todo-filter-wrapper>
                <p data-todo-filter-status></p>
                <div data-todo-filter-panel>
                    <form data-todo-filter-form data-current-filter="open">
                        <button type="button" data-todo-filter data-filter="open" data-active="true"></button>
                        <button type="button" data-todo-filter data-filter="assigned" data-active="false"></button>
                    </form>
                </div>
                <div data-todo-section="open"></div>
                <div data-todo-section="assigned"></div>
            </section>
        `;

        initTodoFilters(document);

        const form = document.querySelector('[data-todo-filter-form]');
        const assignedButton = document.querySelector('[data-filter="assigned"]');

        assignedButton.dispatchEvent(new Event('click', { bubbles: true }));

        expect(form.dataset.currentFilter).toBe('assigned');
    });
});

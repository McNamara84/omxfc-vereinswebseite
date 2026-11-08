import { beforeEach, expect, test, vi } from 'vitest';
import { page, userEvent } from 'vitest/browser';
import Alpine from 'alpinejs';
import { navigationSpotlight } from '../../../resources/js/navigation-search.js';

// Exercise the real provider and native dialog with the component's Alpine bindings.
const trigger = () => page.getByRole('button', { name: 'Suche öffnen' });
const input = () => page.getByRole('searchbox', { name: 'Seite suchen' });
const dialog = () => page.getByRole('dialog', { name: 'Navigationssuche', includeHidden: true });

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify([
        { name: 'Chronik', link: '/chronik' },
        { name: 'Termine', link: '/termine' },
    ]), { headers: { 'Content-Type': 'application/json' } })));
    Alpine.data('navigationSpotlight', navigationSpotlight);
    document.body.innerHTML = `
        <button data-testid="navigation-search-trigger">Suche öffnen</button>
        <div x-data="navigationSpotlight({ url: '/search', scope: 'member' })"
             x-on:keydown.window.capture="shortcut($event)">
            <dialog x-ref="dialog" aria-label="Navigationssuche"
                    x-on:cancel.prevent.stop="close()"
                    x-on:keydown.escape.prevent.stop="close()"
                    x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
                    x-on:toggle="if ($el.open) $refs.input.focus()">
                <label>Seite suchen <input type="search" x-ref="input" x-model="value" x-on:input="schedule()"></label>
                <button x-on:click="close()">Suche schließen</button>
                <ul x-ref="results">
                    <template x-for="item in results" :key="item.link">
                        <li><a :href="item.link" x-text="item.name"></a></li>
                    </template>
                </ul>
            </dialog>
        </div>`;
    Alpine.initTree(document.querySelector('[x-data]'));
});

async function openSearch() {
    await trigger().click();
    await userEvent.keyboard('{Control>}k{/Control}');
    await expect.element(dialog()).toBeVisible();
    await expect.element(input()).toHaveFocus();
    expect(document.querySelector('dialog').matches(':modal')).toBe(true);
    // Wait for the component's focus settling before intentional result navigation.
    await expect.poll(() => Alpine.$data(document.querySelector('[x-data]')).focusTimer, {
        message: 'Der native Dialog und der verzögerte Fokusnachlauf sind bereit',
    }).toBeNull();
}

test('öffnet einen nativen modalen Dialog per Tastatur und fokussiert die Suche', async () => {
    await openSearch();
    expect(fetch).not.toHaveBeenCalled();
});

test('sucht nach Eingabe und navigiert mit Pfeiltasten durch echte Links', async () => {
    await openSearch();
    await input().fill('Chronik');
    await expect.element(page.getByRole('link', { name: 'Chronik' })).toBeVisible();
    expect(fetch).toHaveBeenCalledExactlyOnceWith('/search?search=Chronik&scope=member', expect.any(Object));
    await userEvent.keyboard('{ArrowDown}');
    await expect.element(page.getByRole('link', { name: 'Chronik' })).toHaveFocus();
    await userEvent.keyboard('{ArrowDown}');
    await expect.element(page.getByRole('link', { name: 'Termine' })).toHaveFocus();
    await userEvent.keyboard('{ArrowDown}');
    await expect.element(page.getByRole('link', { name: 'Chronik' })).toHaveFocus();
    await userEvent.keyboard('{ArrowUp}');
    await expect.element(page.getByRole('link', { name: 'Termine' })).toHaveFocus();
});

test('schließt mit Escape, gibt den Fokus zurück und öffnet ohne alten Suchzustand', async () => {
    await openSearch();
    await input().fill('Chronik');
    await expect.element(page.getByRole('link', { name: 'Chronik' })).toBeVisible();
    await userEvent.keyboard('{Escape}');
    await expect.element(dialog()).not.toBeVisible();
    await expect.element(trigger()).toHaveFocus();
    await openSearch();
    await expect.element(input()).toHaveValue('');
    await expect.element(page.getByRole('link', { name: 'Chronik' })).not.toBeInTheDocument();
    await page.getByRole('button', { name: 'Suche schließen' }).click();
    await expect.element(dialog()).not.toBeVisible();
    await expect.element(trigger()).toHaveFocus();
});

<div
    x-data="navigationSpotlight(@js(['url' => $url, 'scope' => $attributes->get('scope', 'public')]))"
    x-on:keydown.window.capture="shortcut($event)"
    x-on:mary-search-open.window="show()"
    x-on:livewire:navigating.document="close(false)"
    x-on:pagehide.window="close(false)"
>
    <dialog
        id="navigation-search-dialog"
        x-ref="dialog"
        class="modal transition-none!"
        aria-labelledby="navigation-search-title"
        x-on:cancel.prevent.stop="close()"
        x-on:keydown.escape.prevent.stop="close()"
        x-on:keydown.down.prevent="move(1)"
        x-on:keydown.up.prevent="move(-1)"
        x-on:toggle="if ($el.open) $refs.input.focus()"
        x-on:click="if ($event.target === $el) close()"
    >
        <div class="modal-box transition-none! max-h-[85dvh] w-[calc(100%_-_2rem)] max-w-2xl space-y-4 bg-base-100 text-base-content">
            <div class="flex items-center justify-between gap-3">
                <h2 id="navigation-search-title" class="font-display text-xl font-semibold">Seite oder Funktion suchen</h2>
                <button type="button" class="btn btn-ghost btn-circle min-h-11 min-w-11" aria-label="Suche schließen" x-on:click="close()">
                    <x-mary-icon name="o-x-mark" class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>
            <label for="navigation-search-input" class="sr-only">Seite oder Funktion suchen</label>
            <input id="navigation-search-input" x-ref="input" x-model="value" x-on:input="schedule()"
                type="search" maxlength="100" autocomplete="off" autofocus class="input input-bordered w-full"
                placeholder="Zum Beispiel Baxx, Mitgliederkarte oder Termine" aria-describedby="navigation-search-help"
                x-bind:aria-busy="loading.toString()" />
            <p id="navigation-search-help" class="text-sm text-base-content/80">Gib mindestens zwei Zeichen ein. Mit den Pfeiltasten wählst du einen Treffer aus.</p>
            <div role="status" aria-live="polite" aria-atomic="true" class="text-sm">
                <span x-show="loading" x-cloak>Suche läuft …</span>
                <span x-show="!loading && !error && searched" x-cloak x-text="results.length ? results.length + ' Treffer gefunden.' : 'Keine passenden Seiten gefunden.'"></span>
            </div>
            <p role="alert" x-show="error" x-cloak x-text="error" class="text-error"></p>
            <ul x-ref="results" class="space-y-1" data-testid="navigation-search-results">
                <template x-for="item in results" :key="item.link">
                    <li>
                        <a x-bind:href="item.link" wire:navigate x-on:click="close(false)"
                            class="block rounded-xl px-4 py-3 hover:bg-base-200 focus-visible:outline-2 focus-visible:outline-primary">
                            <span class="block font-semibold" x-html="item.name"></span>
                            <span class="block text-sm text-base-content/80" x-html="item.description"></span>
                        </a>
                    </li>
                </template>
            </ul>
        </div>
    </dialog>
</div>

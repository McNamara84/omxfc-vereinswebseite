<button type="button" class="btn btn-ghost min-h-11 min-w-11 px-2"
    x-data x-on:click.stop="$dispatch('mary-search-open')" aria-label="Suche öffnen"
    data-testid="navigation-search-trigger" data-tour-key="navigation-search"
    aria-haspopup="dialog" aria-controls="navigation-search-dialog" aria-keyshortcuts="Control+K Meta+K">
    <x-mary-icon name="o-magnifying-glass" class="h-5 w-5" aria-hidden="true" />
    <span class="hidden 2xl:inline">Suche</span>
    <kbd class="hidden 2xl:inline text-xs" x-text="/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Strg+K'">Strg+K</kbd>
</button>

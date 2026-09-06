<div
    class="contents"
    x-data="{
        dark: document.documentElement.dataset.theme === 'coffee',
        syncTheme(theme = document.documentElement.dataset.theme) {
            this.dark = theme === 'coffee';
        }
    }"
    x-on:theme-changed.window="syncTheme($event.detail)"
    x-on:storage.window="if (['mary-theme', 'mary-class'].includes($event.key)) { $nextTick(() => syncTheme()) }"
>
    <x-mary-button
        type="button"
        class="tooltip tooltip-bottom btn-circle btn-ghost min-h-11 min-w-11 border border-base-content/10 bg-base-100/65"
        data-testid="theme-toggle"
        data-theme-toggle-trigger
        aria-label="Dunkles Design aktivieren"
        aria-pressed="false"
        x-bind:aria-label="dark ? 'Helles Design aktivieren' : 'Dunkles Design aktivieren'"
        x-bind:aria-pressed="dark.toString()"
        x-bind:data-tip="dark ? 'Helles Design aktivieren' : 'Dunkles Design aktivieren'"
        x-on:click="try { localStorage.setItem('omxfc-theme-explicit', '1') } catch {} finally { $dispatch('mary-toggle-theme') }"
    >
        <x-mary-icon x-show="!dark" name="o-moon" class="h-5 w-5" aria-hidden="true" />
        <x-mary-icon x-show="dark" x-cloak name="o-sun" class="h-5 w-5" aria-hidden="true" />
    </x-mary-button>

    <x-mary-theme-toggle
        id="omxfc-theme-controller"
        light-theme="caramellatte"
        dark-theme="coffee"
        light-class=""
        dark-class="dark"
        class="hidden"
    />
</div>

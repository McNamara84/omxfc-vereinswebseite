<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\ThemeToggle;

/**
 * maryUI theme toggle with external preference synchronisation.
 *
 * The upstream component owns the persisted theme. This adapter only lets the
 * system-preference/storage adapter align that same Alpine state before the
 * next manual toggle.
 */
class NavigationThemeToggle extends ThemeToggle
{
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <div>
                    <label
                        for="{{ $uuid }}"
                        x-data="{
                            theme: $persist(window.matchMedia('(prefers-color-scheme: dark)').matches ? '{{ $darkTheme }}' : '{{ $lightTheme }}').as('mary-theme'),
                            class: $persist(window.matchMedia('(prefers-color-scheme: dark)').matches ? '{{ $darkClass }}' : '{{ $lightClass }}').as('mary-class'),
                            init() {
                                if (this.theme == '{{ $darkTheme }}') {
                                    this.$refs.sun.classList.add('swap-off');
                                    this.$refs.sun.classList.remove('swap-on');
                                    this.$refs.moon.classList.add('swap-on');
                                    this.$refs.moon.classList.remove('swap-off');
                                }
                                this.setToggle()
                            },
                            setToggle() {
                                document.documentElement.setAttribute('data-theme', this.theme)
                                document.documentElement.setAttribute('class', this.class)
                                this.$dispatch('theme-changed', this.theme)
                                this.$dispatch('theme-changed-class', this.class)
                            },
                            syncExternal(payload) {
                                if (!payload || !['{{ $lightTheme }}', '{{ $darkTheme }}'].includes(payload.theme)) return
                                this.theme = payload.theme
                                this.class = payload.class ?? (payload.theme == '{{ $darkTheme }}' ? '{{ $darkClass }}' : '{{ $lightClass }}')
                            },
                            toggle() {
                                this.theme = this.theme == '{{ $lightTheme }}' ? '{{ $darkTheme }}' : '{{ $lightTheme }}'
                                this.class = this.theme == '{{ $lightTheme }}' ? '{{ $lightClass }}' : '{{ $darkClass }}'
                                this.setToggle()
                            }
                        }"
                        @mary-toggle-theme.window="toggle()"
                        @omxfc-theme-sync.window="syncExternal($event.detail)"
                        {{ $attributes->class("swap swap-rotate") }}
                    >
                        <input id="{{ $uuid }}" type="checkbox" class="theme-controller opacity-0" @click="toggle()" :value="theme" />
                        <x-mary-icon x-ref="sun" name="o-sun" class="swap-on" />
                        <x-mary-icon x-ref="moon" name="o-moon" class="swap-off" />
                    </label>
                </div>
                <script>
                    (() => {
                        try {
                            const root = document.documentElement
                            const storedTheme = window.localStorage.getItem("mary-theme")
                            const storedClass = window.localStorage.getItem("mary-class")

                            if (root && storedTheme !== null) {
                                root.setAttribute("data-theme", storedTheme.replaceAll("\"", ""))
                            }

                            if (root && storedClass !== null) {
                                root.setAttribute("class", storedClass.replaceAll("\"", ""))
                            }
                        } catch {}
                    })()
                </script>
            HTML;
    }
}

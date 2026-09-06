<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\MenuSub;

/**
 * maryUI menu-sub with attribute forwarding for tour and accessibility hooks.
 *
 * maryUI 2.9 does not render the component attribute bag on its root element.
 * This narrow adapter keeps the upstream behaviour while making data-* and
 * Alpine attributes available to the application shell.
 */
class NavigationMenuSub extends MenuSub
{
    public function render(): View|Closure|string
    {
        if ($this->hidden === true) {
            return '';
        }

        return <<<'BLADE'
                @aware(['horizontal' => false, 'activeBgColor' => 'bg-base-300'])

                @php
                    $submenuActive = Str::contains($slot, 'mary-active-menu');
                @endphp

                @if ($slot->isNotEmpty())
                <li
                    {{ $attributes->class(['menu-disabled' => $disabled, 'static!' => $horizontal]) }}
                    x-data="
                    {
                        show: @if(($submenuActive || $open) && !$horizontal) true @else false @endif,
                        toggle(){
                            if (this.collapsed) {
                                this.show = true
                                $dispatch('menu-sub-clicked');
                                return
                            }

                            this.show = !this.show
                        }
                    }"
                >
                    <details
                        :open="show"
                        @click.stop
                        @if($submenuActive && !$horizontal) open @endif
                        @if($horizontal) @click.outside="show = false" @endif
                    >
                        <summary
                            @click.prevent="toggle()"
                            aria-label="{{ $title }}"
                            title="{{ $title }}"
                            @class(["hover:text-inherit px-4 py-1.5 my-0.5 text-inherit", $activeBgColor => $submenuActive])
                            @if($horizontal) x-ref="sub" @endif
                        >
                            @if($icon)
                                <x-mary-icon :name="$icon" @class(['inline-flex my-0.5', $iconClasses]) />
                            @endif

                            <span class="mary-hideable whitespace-nowrap truncate">{{ $title }}</span>
                        </summary>

                        <ul @class(["mary-hideable",  "z-10 mt-1" => $horizontal]) @if($horizontal) x-anchor.bottom-start="$refs.sub" @endif>
                            {{ $slot }}
                        </ul>
                    </details>
                </li>
                @endif
            BLADE;
    }
}

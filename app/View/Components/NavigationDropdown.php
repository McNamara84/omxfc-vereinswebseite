<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Dropdown;

/**
 * maryUI dropdown with valid menu list markup for the navigation shell.
 *
 * maryUI 2.9 wraps the slot in a div below the ul. Keeping the slot elements
 * as direct children preserves the component behaviour and valid list/menu
 * semantics for assistive technology.
 */
class NavigationDropdown extends Dropdown
{
    public function render(): View|Closure|string
    {
        return <<<'BLADE'
            <details
                x-data="{open: false}"
                @click.outside="open = false"
                :open="open"
                @class([
                    'overflow-visible',
                    'dropdown',
                    'dropdown-end' => ($noXAnchor && $right),
                    'dropdown-top' => ($noXAnchor && $top),
                    'dropdown-bottom' => $noXAnchor,
                ])
            >
                @if($trigger)
                    <summary x-ref="button" @click.prevent="open = !open" {{ $trigger->attributes->class(['list-none']) }}>
                        {{ $trigger }}
                    </summary>
                @else
                    <summary x-ref="button" @click.prevent="open = !open" {{ $attributes->class(["btn"]) }}>
                        {{ $label }}
                        <x-mary-icon :name="$icon" />
                    </summary>
                @endif

                <ul
                    wire:key="dropdown-slot-{{ $uuid }}"
                    @class([
                        'p-2','shadow','menu','z-[1]','border-[length:var(--border)]','border-base-content/10','bg-base-100', 'rounded-box','w-auto','min-w-max',
                        'dropdown-content' => $noXAnchor,
                        $maxHeight => $scroll,
                        'overflow-y-auto' => $scroll,
                    ])
                    @click="open = false"
                    @if(!$noXAnchor)
                        x-anchor.{{ $right ? 'bottom-end' : 'bottom-start' }}="$refs.button"
                    @endif
                >
                    {{ $slot }}
                </ul>
            </details>
        BLADE;
    }
}

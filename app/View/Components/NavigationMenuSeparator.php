<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\MenuSeparator;

/**
 * maryUI menu separator with list-valid markup for navigation menus.
 */
class NavigationMenuSeparator extends MenuSeparator
{
    public function render(): View|Closure|string
    {
        return <<<'BLADE'
            <li role="separator" aria-hidden="true" class="my-3">
                <hr class="border-t-[length:var(--border)] border-base-content/10" />
            </li>

            @if($title)
                <li {{ $attributes->class(["menu-title text-inherit uppercase"]) }}>
                    <div class="flex items-center gap-2">
                        @if($icon)
                            <x-mary-icon :name="$icon" @class([$iconClasses]) />
                        @endif

                        {{ $title }}
                    </div>
                </li>
            @endif
        BLADE;
    }
}

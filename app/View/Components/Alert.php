<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Alert as MaryAlert;

/**
 * Überschreibt die maryUI Alert-Komponente, um dem Dismiss-Button
 * ein aria-label="Schließen" hinzuzufügen (WCAG AA button-name).
 */
class Alert extends MaryAlert
{
    public function render(): View|Closure|string
    {
        return <<<'BLADE'
                <div
                    wire:key="{{ $uuid }}"
                    {{ $attributes->whereDoesntStartWith('class') }}
                    {{ $attributes->class(['alert rounded-md', 'shadow-md' => $shadow])}}
                    x-data="{ show: true }" x-show="show"
                >
                    @if($icon)
                        <x-mary-icon :name="$icon" class="self-center" />
                    @endif

                    @if($title)
                        <div>
                            <div @class(["font-bold" => $description])>{{ $title }}</div>
                            <div class="text-xs">{{ $description }}</div>
                        </div>
                    @else
                        <span>{{ $slot }}</span>
                    @endif

                    <div class="flex items-center gap-3">
                        {{ $actions }}
                    </div>

                    @if($dismissible)
                        <x-mary-button icon="o-x-mark" @click="show = false" class="btn-xs btn-circle btn-ghost static self-start end-0" aria-label="Schließen" />
                    @endif
                </div>
            BLADE;
    }
}

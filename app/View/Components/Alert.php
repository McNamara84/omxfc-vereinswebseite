<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Schlanke Alert-Komponente ohne harte Abhängigkeit auf MaryUI.
 */
class Alert extends Component
{
    public function __construct(
        public ?string $icon = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $dismissible = false,
        public bool $shadow = true,
    ) {}

    public function render(): View|Closure|string
    {
        return <<<'BLADE'
                <div
                    {{ $attributes->whereDoesntStartWith('class') }}
                    {{ $attributes->class(['alert rounded-md', 'shadow-md' => $shadow]) }}
                    x-data="{ show: true }" x-show="show"
                >
                    @if($icon)
                        <span aria-hidden="true" class="self-center text-sm">•</span>
                    @endif

                    @if($title || $description)
                        <div>
                            <div @class(["font-bold" => $description])>{{ $title }}</div>
                            @if($description)
                                <div class="text-xs">{{ $description }}</div>
                            @endif
                        </div>
                    @else
                        <span>{{ $slot }}</span>
                    @endif

                    @isset($actions)
                        <div class="flex items-center gap-3">
                            {{ $actions }}
                        </div>
                    @endisset

                    @if($dismissible)
                        <button type="button" @click="show = false" class="btn btn-ghost btn-xs btn-circle static self-start end-0" aria-label="Schließen">
                            <span aria-hidden="true">×</span>
                        </button>
                    @endif
                </div>
            BLADE;
    }
}

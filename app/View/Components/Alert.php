<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Eigenständige Alert-Komponente für Titel-, Text-, Actions- und Dismiss-Rendering ohne MaryUI-Alert-Komponente.
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
                @php($hasBody = trim((string) $slot) !== '')

                <div {{ $attributes->class(['alert rounded-md', 'shadow-md' => $shadow])->merge(['x-data' => '{ show: true }', 'x-show' => 'show']) }}>
                    @if($icon)
                        <x-icon :name="$icon" class="mt-0.5 h-5 w-5 shrink-0 self-start" />
                    @endif

                    @if($title || $description || $hasBody)
                        <div>
                            @if($title)
                                <div class="font-bold">{{ $title }}</div>
                            @endif

                            @if($description)
                                <div class="text-xs">{{ $description }}</div>
                            @endif

                            @if($hasBody)
                                <div @class(['mt-1 text-xs' => $description, 'text-sm' => ! $description])>{{ $slot }}</div>
                            @endif
                        </div>
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

@props(['title', 'href' => null, 'srText' => null, 'icon' => null])
@php
    $titleId = \Illuminate\Support\Str::slug($title, '-') . '-' . uniqid();
@endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-[1.75rem] border border-base-content/10 bg-base-100/95 p-6 shadow-xl shadow-base-content/5 transition duration-200 hover:-translate-y-0.5 hover:border-primary/20 hover:shadow-2xl']) }} role="region" aria-labelledby="{{ $titleId }}">
@else
<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-[1.75rem] border border-base-content/10 bg-base-100/95 p-6 shadow-xl shadow-base-content/5']) }} role="region" aria-labelledby="{{ $titleId }}">
@endif
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-primary/35 via-accent/25 to-transparent"></div>

    <div class="relative flex h-full flex-col gap-6">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-2">
                <h2 id="{{ $titleId }}" class="font-display text-xl font-semibold tracking-tight text-base-content transition-colors group-hover:text-primary">{{ $title }}</h2>

                @isset($description)
                    <div class="text-sm leading-relaxed text-base-content/70">
                        {{ $description }}
                    </div>
                @elseif(trim((string) $slot) !== '')
                    <div class="text-sm leading-relaxed text-base-content/70">
                        {{ $slot }}
                    </div>
                @endif
            </div>

            @if($icon)
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15">
                    <x-icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
        </div>

        @isset($value)
            <div class="mt-auto flex items-end justify-between gap-4">
                <div class="font-display text-4xl font-bold tracking-tight text-base-content">
                    {{ $value }}
                </div>

                @if($href)
                    <span class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/45">
                        Öffnen
                        <x-icon name="o-chevron-right" class="h-4 w-4" />
                    </span>
                @endif
            </div>
        @else
            <div class="mt-auto">
                {{ $slot }}
            </div>
        @endisset
    </div>

    @if($srText)
        <span class="sr-only">{{ $srText }}</span>
    @endif
    @isset($actions)
        <div class="mt-4 flex gap-2" aria-label="{{ __('Aktionen für :title', ['title' => $title]) }}">
            {{ $actions }}
        </div>
    @endisset
@if($href)
</a>
@else
</div>
@endif

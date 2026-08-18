@props(['metric'])

@php
    $cardClasses = [
        'group flex min-h-28 items-start gap-3 rounded-2xl border bg-base-100/80 p-4 transition',
        'border-warning/35 bg-warning/5' => ($metric['tone'] ?? 'neutral') === 'warning',
        'border-primary/30 bg-primary/5' => ($metric['tone'] ?? 'neutral') === 'attention',
        'border-base-content/10' => ($metric['tone'] ?? 'neutral') === 'neutral',
        'hover:border-primary/30 hover:shadow-md' => filled($metric['href']),
    ];
@endphp

@if($metric['href'])
    <a href="{{ $metric['href'] }}" wire:navigate @class($cardClasses) data-testid="dashboard-metric-{{ $metric['key'] }}">
        <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <x-icon :name="$metric['icon']" class="h-4.5 w-4.5" />
        </span>
        <span class="min-w-0">
            <span class="block text-xs font-semibold uppercase tracking-wide text-base-content/55">{{ $metric['title'] }}</span>
            <span class="mt-1 block font-display text-xl font-bold leading-tight text-base-content">{{ $metric['value'] }}</span>
            <span class="mt-1 block line-clamp-2 text-xs leading-snug text-base-content/65">{{ $metric['description'] }}</span>
        </span>
    </a>
@else
    <div @class($cardClasses) data-testid="dashboard-metric-{{ $metric['key'] }}">
        <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/60">
            <x-icon :name="$metric['icon']" class="h-4.5 w-4.5" />
        </span>
        <span class="min-w-0">
            <span class="block text-xs font-semibold uppercase tracking-wide text-base-content/55">{{ $metric['title'] }}</span>
            <span class="mt-1 block font-display text-xl font-bold leading-tight text-base-content">{{ $metric['value'] }}</span>
            <span class="mt-1 block line-clamp-2 text-xs leading-snug text-base-content/65">{{ $metric['description'] }}</span>
        </span>
    </div>
@endif

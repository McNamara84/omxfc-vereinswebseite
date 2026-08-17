<x-ui.panel title="Schnellstart" data-testid="dashboard-quick-actions" class="p-5">
    <nav class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-2" aria-label="Schnellstart">
        @foreach($quickActions as $action)
            <a href="{{ $action['href'] }}" wire:navigate class="group flex items-center gap-2 rounded-xl border border-base-content/10 bg-base-200/35 px-3 py-3 text-sm font-semibold transition hover:border-primary/30 hover:bg-primary/5 hover:text-primary">
                <x-icon :name="$action['icon']" class="h-4.5 w-4.5 shrink-0 text-primary" />
                <span class="truncate">{{ $action['title'] }}</span>
                <x-icon name="o-chevron-right" class="ml-auto h-4 w-4 shrink-0 text-base-content/30 group-hover:text-primary" />
            </a>
        @endforeach
    </nav>
</x-ui.panel>

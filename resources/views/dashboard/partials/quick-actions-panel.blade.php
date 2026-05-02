<x-ui.panel title="Schnellstart" description="Beliebte Wege zurück in laufende Aktionen und Inhalte." data-testid="dashboard-quick-actions">
    <div class="grid gap-3">
        @foreach($quickActions as $action)
            <a href="{{ $action['href'] }}" wire:navigate class="group flex items-start gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/70 px-4 py-4 transition hover:-translate-y-0.5 hover:border-primary/20 hover:shadow-lg">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15">
                    <x-icon :name="$action['icon']" class="h-5 w-5" />
                </span>

                <span class="min-w-0 flex-1 space-y-1">
                    <span class="flex flex-wrap items-center gap-2 font-semibold text-base-content transition-colors group-hover:text-primary">
                        <span>{{ $action['title'] }}</span>
                        @if($action['badge'] ?? null)
                            <span class="badge badge-primary badge-sm rounded-full">{{ $action['badge'] }}</span>
                        @endif
                    </span>
                    <span class="block text-sm leading-relaxed text-base-content/70">{{ $action['description'] }}</span>
                </span>

                <x-icon name="o-chevron-right" class="mt-1 h-5 w-5 shrink-0 text-base-content/35 transition-colors group-hover:text-primary" />
            </a>
        @endforeach
    </div>
</x-ui.panel>
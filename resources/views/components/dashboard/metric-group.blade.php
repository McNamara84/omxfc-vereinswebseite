@props(['group'])

<section aria-labelledby="dashboard-metrics-{{ $group['key'] }}" data-testid="dashboard-metric-group-{{ $group['key'] }}">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <h2 id="dashboard-metrics-{{ $group['key'] }}" class="font-display text-xl font-semibold tracking-tight text-base-content">
            {{ $group['title'] }}
        </h2>
        <p class="text-xs text-base-content/55">{{ $group['description'] }}</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($group['metrics'] as $metric)
            <x-dashboard.metric :metric="$metric" />
        @endforeach
    </div>
</section>

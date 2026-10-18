@props(['group', 'heading' => true])

<section @if($heading) aria-labelledby="dashboard-metrics-{{ $group['key'] }}" @else aria-label="{{ $group['title'] }}" @endif data-testid="dashboard-metric-group-{{ $group['key'] }}">
    @if($heading)
    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <h2 id="dashboard-metrics-{{ $group['key'] }}" class="font-display text-xl font-semibold tracking-tight text-base-content">
            {{ $group['title'] }}
        </h2>
        <p class="text-xs text-base-content/75">{{ $group['description'] }}</p>
    </div>
    @endif
    <div class="grid gap-3 sm:grid-cols-2 {{ count($group['metrics']) > 2 ? 'xl:grid-cols-4' : '' }}">
        @foreach($group['metrics'] as $metric)
            <x-dashboard.metric :metric="$metric" />
        @endforeach
    </div>
</section>

@props(['tasks'])

<section class="rounded-2xl border border-base-content/10 bg-base-100/85 p-4 shadow-sm" aria-labelledby="dashboard-tasks-title" data-testid="dashboard-tasks">
    <div class="mb-3 flex items-center gap-2">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <x-icon name="o-check-circle" class="h-4.5 w-4.5" />
        </span>
        <h2 id="dashboard-tasks-title" class="font-display text-lg font-semibold">Zu erledigen</h2>
    </div>

    @if($tasks === [])
        <p class="rounded-xl bg-success/8 px-3 py-2 text-sm text-base-content/70">Aktuell nichts offen.</p>
    @else
        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
            @foreach($tasks as $task)
                @php($taskTag = filled($task['href'] ?? null) ? 'a' : 'div')
                <{{ $taskTag }}
                    @if($taskTag === 'a')
                        href="{{ $task['href'] }}" wire:navigate
                    @endif
                    @class([
                    'flex items-center gap-3 rounded-xl border px-3 py-2.5 transition hover:border-primary/30 hover:bg-primary/5',
                    'border-primary/25 bg-primary/5' => ($task['count'] ?? 0) > 0,
                    'border-base-content/10 bg-base-200/35' => ($task['count'] ?? 0) === 0,
                    'hover:border-base-content/10 hover:bg-base-200/35' => $taskTag === 'div',
                ]) data-testid="dashboard-task-{{ $task['key'] }}">
                    <x-icon :name="$task['icon']" class="h-5 w-5 shrink-0 text-primary" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ $task['title'] }}</span>
                        <span class="block truncate text-xs text-base-content/55">{{ $task['description'] }}</span>
                    </span>
                    @if(($task['count'] ?? 0) > 0)
                        <span class="badge badge-primary badge-sm rounded-full">{{ $task['count'] }}</span>
                    @endif
                </{{ $taskTag }}>
            @endforeach
        </div>
    @endif
</section>

@can('access-rpg-combats')
    <section class="rounded-xl border border-base-300 p-5 space-y-3" aria-label="Persönliche Übungskämpfe">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold text-lg">Übungskämpfe</h2><a class="link" href="{{ route('rpg.combats.index') }}">Alle Kämpfe</a></div>
        @forelse(app(\App\Services\RpgCombat\CombatQuery::class)->hints(auth()->user()) as $hint)
            <div><p><a class="link" href="{{ $hint['url'] }}">{{ $hint['label'] }}</a> · {{ \App\Models\RpgCombat::statusLabel($hint['status']) }}</p>
                @foreach($hint['tasks'] as $task)
                    <p class="text-sm">{{ $task['label'] }} · {{ $task['due_at'] ? 'bis '.$task['due_at']->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr' : 'pausiert bis zur Regelauslegung' }}</p>
                @endforeach
            </div>
        @empty
            <p>Keine offenen Übungskämpfe. <a class="link" href="{{ route('rpg.combats.create') }}">Charakter herausfordern</a></p>
        @endforelse
    </section>
@endcan

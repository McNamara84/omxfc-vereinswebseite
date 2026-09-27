@can('access-rpg-checks')
    @php($checkHints = app(\App\Services\RpgCheckQuery::class)->listing(auth()->user(), hints: true))
    <section x-data="rpgChecks({{ \Illuminate\Support\Js::from(['initial' => $checkHints, 'url' => route('rpg.checks.hints')]) }})" class="rounded-xl border border-base-300 p-5 space-y-3" aria-label="Persönliche Rollenspiel-Proben">
        @csrf
        <div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold text-lg">Rollenspiel-Proben</h2><a class="link" href="{{ route('rpg.checks.index') }}">Alle Proben</a></div>
        <div role="alert" x-cloak x-show="error" x-text="error"></div>
        <p class="sr-only" aria-live="polite" x-text="announcement"></p>
        <p><span x-text="data.total"></span> offene Aufforderungen</p>
        <ul class="space-y-2"><template x-for="check in data.checks" :key="check.id"><li><a class="link" :href="check.url"><span x-text="check.description"></span> · <span x-text="check.participants.map(p => p.character_name).join(', ')"></span></a><p class="text-sm" x-text="check.status_label"></p></li></template></ul>
        <template x-if="data.recent?.length"><div><h3 class="font-semibold">Jüngste Ergebnisse</h3><ul><template x-for="check in data.recent" :key="check.id"><li><a class="link" :href="check.url" x-text="check.description"></a></li></template></ul></div></template>
        <button type="button" class="btn btn-ghost btn-sm" @click="refresh()">Jetzt aktualisieren</button>
    </section>
@endcan

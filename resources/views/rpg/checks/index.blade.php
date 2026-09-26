<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Proben" eyebrow="AG Rollenspiel" description="Offene Würfelaufforderungen und dein Probenverlauf." />
        <div class="flex flex-wrap gap-2 my-4">
            <a class="btn btn-ghost" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a>
            @can('manage-rpg-checks')<a class="btn btn-primary" href="{{ route('rpg.checks.create') }}">Probe anfordern</a>@endcan
        </div>
        <form method="GET" class="grid gap-3 mb-5 sm:grid-cols-2">
            <label class="fieldset">Ansicht<select class="select w-full" name="tab"><option value="open" @selected(($filters['tab'] ?? 'open') === 'open')>Offen</option><option value="history" @selected(($filters['tab'] ?? '') === 'history')>Verlauf</option></select></label>
            <label class="fieldset">Charakter<select class="select w-full" name="character_id"><option value="">Alle eigenen / berechtigten Charaktere</option>@foreach($characters as $character)<option value="{{ $character['id'] }}" @selected(($filters['character_id'] ?? '') == $character['id'])>{{ $character['label'] }}</option>@endforeach</select></label>
            <label class="fieldset">Probenvergleich<select class="select w-full" name="mode"><option value="">Alle</option><option value="fixed" @selected(($filters['mode'] ?? '') === 'fixed')>Gegen Schwierigkeit</option><option value="opposed" @selected(($filters['mode'] ?? '') === 'opposed')>Widerstand</option></select></label>
            <label class="fieldset">Sichtbarkeit<select class="select w-full" name="visibility"><option value="">Alle</option><option value="open" @selected(($filters['visibility'] ?? '') === 'open')>Offen</option><option value="hidden" @selected(($filters['visibility'] ?? '') === 'hidden')>Verdeckt</option></select></label>
            <button class="btn btn-outline" type="submit">Filtern</button>
        </form>
        <div x-data="rpgChecks({{ \Illuminate\Support\Js::from(['initial' => $data, 'url' => route('rpg.checks.index', $filters)]) }})" class="space-y-4">
            @csrf
            <div role="alert" x-cloak x-show="error" x-text="error" class="alert alert-error"></div>
            <p class="sr-only" aria-live="polite" x-text="announcement"></p>
            <button type="button" class="btn btn-ghost btn-sm" @click="refresh()" :disabled="busy">Jetzt aktualisieren</button>
            @include('rpg.checks.partials.cards')
            <nav aria-label="Proben-Seiten" class="flex flex-wrap items-center gap-3">
                <a x-show="data.page > 1" class="btn btn-sm" :href="new URLSearchParams({ ...{{ \Illuminate\Support\Js::from($filters) }}, page: data.page - 1 }).toString().replace(/^/, '?')">Zurück</a>
                <span>Seite <span x-text="data.page"></span> von <span x-text="data.last_page"></span></span>
                <a x-show="data.page < data.last_page" class="btn btn-sm" :href="new URLSearchParams({ ...{{ \Illuminate\Support\Js::from($filters) }}, page: data.page + 1 }).toString().replace(/^/, '?')">Weiter</a>
            </nav>
        </div>
    </x-member-page>
</x-member-layout>

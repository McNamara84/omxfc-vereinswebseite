<x-member-layout>
    <x-member-page class="max-w-4xl">
        <x-ui.page-header title="Probe" eyebrow="AG Rollenspiel" description="Würfelauftrag und Ergebnis" />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.checks.index') }}">Alle Proben</a>
        <div x-data="rpgChecks({{ \Illuminate\Support\Js::from(['initial' => ['checks' => [$data]], 'url' => $data['url'], 'detail' => true]) }})" class="space-y-4">
            @csrf
            <div role="alert" x-cloak x-show="error" x-text="error" class="alert alert-error"></div>
            <p class="sr-only" aria-live="polite" x-text="announcement"></p>
            <button type="button" class="btn btn-ghost btn-sm" @click="refresh()" :disabled="busy">Jetzt aktualisieren</button>
            @include('rpg.checks.partials.cards')
        </div>
    </x-member-page>
</x-member-layout>

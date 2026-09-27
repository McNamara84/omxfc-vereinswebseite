<x-member-layout>
    <x-member-page class="max-w-6xl">
        <x-ui.page-header title="Übungskampf" eyebrow="AG Rollenspiel" />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.combats.index') }}">Alle Kämpfe</a>
        @include('rpg.combats.partials.errors')
        <div data-rpg-combat data-url="{{ request()->fullUrl() }}" data-revision="{{ $combat->revision }}">
            <p data-combat-notice role="status" aria-live="polite"></p>
            <a class="link" data-combat-refresh href="{{ route('rpg.combats.show', $combat) }}">Jetzt aktualisieren</a>
            <div data-combat-content class="space-y-6 mt-4">@include('rpg.combats.partials.fight')</div>
        </div>
    </x-member-page>
</x-member-layout>

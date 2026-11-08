<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="{{ $versions->first()->definition['name'] }}" eyebrow="Eigener RPG-Gegenstand" description="Vorhandene Exemplare behalten ihre gespeicherte Version. Änderungen werden als neue Version angelegt." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.items.index') }}">Gegenstände verwalten</a>
        @include('rpg.progression.partials.messages')
        <div class="flex flex-wrap gap-3 my-4">
            @unless($itemType->archived)<a class="btn btn-primary" href="{{ route('rpg.items.versions.create', $itemType) }}">Neue Version erstellen</a>@endunless
            <form method="POST" action="{{ route('rpg.items.configure', $itemType) }}">@csrf @method('PATCH')<input type="hidden" name="revision" value="{{ $itemType->revision }}" /><input type="hidden" name="starting_equipment" value="{{ $itemType->starting_equipment ? 0 : 1 }}" /><button class="btn btn-outline">{{ $itemType->starting_equipment ? 'Startfreigabe entziehen' : 'Für Startausrüstung freigeben' }}</button></form>
            <form method="POST" action="{{ route('rpg.items.configure', $itemType) }}">@csrf @method('PATCH')<input type="hidden" name="revision" value="{{ $itemType->revision }}" /><input type="hidden" name="archived" value="{{ $itemType->archived ? 0 : 1 }}" /><button class="btn btn-outline">{{ $itemType->archived ? 'Wieder aktivieren' : 'Archivieren' }}</button></form>
        </div>
        <p>Startausrüstung: {{ $itemType->starting_equipment && ! $itemType->archived ? 'Freigegeben' : 'Nicht verfügbar' }}</p>
        @foreach($versions as $version)
            <section class="rounded-xl border border-base-300 p-5 my-4 space-y-2">
                <h2 class="text-xl font-semibold">Version {{ $version->version }} · {{ $version->definition['name'] }}</h2>
                <p>{{ $version->definition['summary'] }}</p>
                <p>TW {{ $version->definition['tw'] }} · {{ $version->definition['bucks'] }} Bucks · Mindestbildung {{ $version->definition['minimumEducation'] }}</p>
                <p>Vergleichsgrundlage: {{ $version->definition['reference'] }}</p>
                <details><summary>Berechnung des Tauschwerts (Regelwerk S. 39)</summary><ol class="list-decimal pl-6">@foreach($version->calculation as $step)<li>{{ $step['label'] }}: {{ $step['value'] }}</li>@endforeach</ol></details>
            </section>
        @endforeach
    </x-member-page>
</x-member-layout>

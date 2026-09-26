<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Erfahrung und Verlauf" eyebrow="Rollenspiel" :description="$character->displayName()" />
        <div class="flex flex-wrap gap-3 my-4"><a class="btn btn-ghost" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a>@can('improve', $character)<a class="btn btn-primary" href="{{ route('rpg.characters.improve', $character) }}">Charakter verbessern</a>@endcan</div>
        @include('rpg.progression.partials.messages')
        @include('rpg.progression.partials.state')
        @can('manage-rpg-experience')
            @php
                $missingBarbar = ($payload['character']['race'] ?? '') === 'Barbar' && ! isset($payload['creation']['attribute_race_modifiers']) && ! isset($payload['progression']['race_modifiers']);
                $missingTargets = array_filter($payload['advantage_effects'] ?? [], fn($effect) => ($effect['target'] ?? '') === '' && ! empty(\App\Support\RpgCharEditorSpecialRules::advantages()[$effect['name']]['targets']));
            @endphp
            @if($missingBarbar || $missingTargets)
                <details class="border border-base-300 rounded-xl p-4 my-4">
                    <summary class="font-semibold cursor-pointer">Fehlende Herkunft des Bestandscharakters ergänzen</summary>
                    <p class="my-3">Dokumentiere die ursprüngliche Wahl anhand des damaligen Charakterbogens. Werte und EP werden nicht verändert. Offene Anträge müssen danach neu eingereicht werden.</p>
                    <form method="POST" action="{{ route('rpg.characters.clarify', $character) }}" class="space-y-3">
                        @csrf<input type="hidden" name="revision" value="{{ $character->revision }}" />
                        @if($missingBarbar)<label class="fieldset">Ursprünglicher Barbarenbonus<select name="barbar_attribute" class="select" required><option value="">Bitte wählen</option>@foreach(\App\Support\RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS as $target)<option value="{{ $target }}">{{ strtoupper($target) }}</option>@endforeach</select></label>@endif
                        @foreach($missingTargets as $index => $effect)
                            <label class="fieldset">{{ $effect['name'] }}: ursprüngliches Ziel<select class="select" name="targets[{{ $index }}]" required><option value="">Bitte wählen</option>@foreach(\App\Support\RpgCharEditorSpecialRules::advantages()[$effect['name']]['targets'] as $target)<option value="{{ $target }}">{{ $target }}</option>@endforeach</select></label>
                        @endforeach
                        <label class="fieldset">Nachweis / Begründung<textarea class="textarea w-full" name="reason" required maxlength="4000"></textarea></label>
                        <button class="btn">Herkunft dokumentieren</button>
                    </form>
                </details>
            @endif
        @endcan
        <h2 class="font-semibold text-xl mt-5">EP-Buchungen</h2>
        @include('rpg.progression.partials.journal')
        <h2 class="font-semibold text-xl my-4">Verbesserungsanträge</h2>
        <ul class="space-y-3">@forelse($requests as $advancement)<li><a class="link" href="{{ route('rpg.advancements.show', $advancement) }}">{{ $advancement->created_at->format('d.m.Y H:i') }} · {{ $advancement->statusLabel() }} · {{ $advancement->cost }} EP</a></li>@empty<li>Noch keine Anträge.</li>@endforelse</ul>
        {{ $requests->links() }}
        @foreach($payload['progression']['metadata_history'] ?? [] as $entry)<p class="my-3 text-sm">Herkunft ergänzt: {{ $entry['date'] }} · {{ $entry['actor'] }} · {{ $entry['reason'] }}</p>@endforeach
    </x-member-page>
</x-member-layout>

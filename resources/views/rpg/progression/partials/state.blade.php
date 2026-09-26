<details class="rounded-lg border border-base-300 p-4 my-4">
    <summary class="cursor-pointer font-semibold">Aktuelle Charakterwerte</summary>
    <div class="grid gap-5 sm:grid-cols-2 mt-3">
        <div><h3 class="font-semibold">Attribute</h3><dl>@foreach($payload['attributes'] ?? [] as $name => $value)<div class="flex gap-3"><dt>{{ strtoupper($name) }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></div>
        <div><h3 class="font-semibold">Fertigkeiten</h3><dl>@foreach($payload['skills'] ?? [] as $skill)<div class="flex gap-3"><dt>{{ $skill['name'] }}</dt><dd>{{ $skill['value'] }}</dd></div>@endforeach</dl></div>
        <div><h3 class="font-semibold">Vorteile</h3><p>{{ implode(', ', $payload['advantages'] ?? []) ?: 'Keine' }}</p>
            @foreach($payload['advantage_effects'] ?? [] as $effect)<p class="text-sm">{{ $effect['name'] }} {{ $effect['target'] ?? '' }}: {{ $effect['justification'] ?? '' }}</p>@endforeach
        </div>
        <div><h3 class="font-semibold">Nachteile</h3><p>{{ implode(', ', $payload['disadvantages'] ?? []) ?: 'Keine' }}</p>
            @foreach($payload['disadvantage_details'] ?? [] as $name => $detail)<p class="text-sm">{{ $name }}: {{ $detail }}</p>@endforeach
        </div>
        @php($psychic = (new \App\Services\RpgCharacterPsychicCalculator)->calculate($payload))
        @if($psychic['powers'])
            <div><h3 class="font-semibold">Psychische Kräfte · {{ $psychic['pep'] }} PEP</h3>
                @foreach($psychic['powers'] as $power)<p>{{ $power['name'] }}: FW {{ $power['value'] }} · {{ $power['usable'] ? 'Einsetzbar' : ($power['value'] === 0 ? 'Latent' : 'WI unter 0') }}</p>@endforeach
            </div>
        @endif
    </div>
</details>

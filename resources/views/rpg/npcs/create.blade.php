<x-member-layout>
    <x-member-page class="max-w-4xl">
        <x-ui.page-header title="NSC aus Regelwerk erstellen" eyebrow="AG Rollenspiel" description="Gemeinsamer AG-Bestand ohne Speicherplatz- oder Baxx-Kosten. Nur die vorgegebenen Vorlagen und ihre ausdrücklich erlaubten Varianten sind verfügbar." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.characters.index') }}">Meine Charaktere und NSCs</a>
        @include('rpg.combats.partials.errors')
        <form method="GET" action="{{ route('rpg.npcs.create') }}" class="flex flex-wrap items-end gap-3 mb-6">
            <label class="block">Vorlage<select name="template_key" class="select w-full" data-testid="npc-template">
                @foreach(collect($templates)->groupBy('group', true) as $group => $rows)
                    <optgroup label="{{ $group }}">@foreach($rows as $key => $row)<option value="{{ $key }}" @selected($selected['key'] === $key)>{{ $row['name'] }}</option>@endforeach</optgroup>
                @endforeach
            </select></label>
            <button class="btn btn-outline" type="submit">Vorlage auswählen</button>
        </form>
        <x-ui.panel :title="$selected['name']" description="Regelwerk Seite {{ $selected['page'] }}">
            <form method="POST" action="{{ route('rpg.npcs.preview') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="template_key" value="{{ $selected['key'] }}">
                <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                @if(!$selected['special'])
                    <label class="block">Optionaler Name<input name="custom_name" class="input w-full" maxlength="255" value="{{ $input['custom_name'] ?? old('custom_name') }}" data-testid="npc-name"></label>
                    <p class="text-sm">Ohne Eigenname wird die Vorlagenbezeichnung verwendet.</p>
                @else
                    <p>Fester Name: <strong>{{ $selected['name'] }}</strong>. Dieser besondere Charakter kann einmal im AG-Bestand vorhanden sein.</p>
                @endif
                @if($selected['key'] === 'bandit')
                    <label class="block">Vorgegebene Waffe<select class="select" name="weapon">@foreach(['schwert'=>'Schwert','messer-dolch'=>'Messer','zwille'=>'Zwille','bogen'=>'Bogen'] as $id => $name)<option value="{{ $id }}" @selected(($input['weapon'] ?? old('weapon', 'schwert')) === $id)>{{ $name }}</option>@endforeach</select></label>
                @endif
                @if($selected['key'] === 'daamure')
                    <label class="block">Rang<select class="select" name="rank">@foreach($ranks as $rank => $values)<option @selected(($input['rank'] ?? old('rank', 'Leq')) === $rank)>{{ $rank }}</option>@endforeach</select></label>
                    <p>Zusätzliche FP: {{ collect($ranks)->map(fn($v, $rank) => $rank.': '.$v['fp'])->join(' · ') }}. Die Punkte müssen vollständig verteilt werden; ein Fertigkeitszuwachs kostet einen FP.</p>
                    <label class="block">Vorgegebene Fachfertigkeit<select class="select" name="profession">@foreach(['Techniker','Wissenschaftler'] as $profession)<option @selected(($input['profession'] ?? old('profession', 'Techniker')) === $profession)>{{ $profession }}</option>@endforeach</select></label>
                    <fieldset class="grid gap-3 sm:grid-cols-2"><legend class="font-semibold">Zusätzliche Fertigkeitspunkte</legend>
                        @foreach($skills as $skill)<label>{{ $skill }}<input type="number" min="0" max="100" name="skill_increases[{{ $skill }}]" value="{{ $input['skill_increases'][$skill] ?? old('skill_increases.'.$skill, 0) }}" class="input w-full" required></label>@endforeach
                    </fieldset>
                    <p>Bildung und Intuition schließen einander aus. Wissenschaftler darf Bildung nicht übersteigen. Neue psychische Kräfte können mit diesem Budget nicht erworben werden.</p>
                    <label class="block">Begründung der FP-Verteilung<textarea class="textarea w-full" name="reason" maxlength="2000">{{ $input['reason'] ?? old('reason') }}</textarea></label>
                @endif
                <button type="submit" class="btn btn-primary">Vorschau berechnen</button>
            </form>
        </x-ui.panel>
        @if($preview)
            <x-ui.panel class="mt-6" title="Verbindliche Vorschau">
                @include('rpg.npcs.partials.profile', ['profile' => $preview['profile']])
                <form method="POST" action="{{ route('rpg.npcs.store') }}" class="mt-4">
                    @csrf
                    @foreach($input as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $nested => $nestedValue)<input type="hidden" name="{{ $key }}[{{ $nested }}]" value="{{ $nestedValue }}">@endforeach
                        @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <button class="btn btn-primary" type="submit" data-testid="npc-create">NSC verbindlich erstellen</button>
                </form>
            </x-ui.panel>
        @endif
    </x-member-page>
</x-member-layout>

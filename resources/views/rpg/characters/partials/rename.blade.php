@php
    $renameFailed = (string) old('rename_character_id') === (string) $character->id && $errors->getBag('rename')->any();
    $renameError = $renameFailed ? $errors->getBag('rename')->first() : null;
    $enteredName = $renameFailed && is_string(old('character_name')) ? old('character_name') : $character->character_name;
    $availableBaxx = $slotSummary['available_baxx'];
    $blockedReason = $character->has_pending_advancement
        ? 'Für diesen Charakter liegt ein offener Verbesserungsantrag vor. Schließe ihn zuerst ab oder ziehe ihn zurück.'
        : ($slotSummary['wallet_warning'] ?: ($availableBaxx < $renameCost ? "Du benötigst {$renameCost} Baxx, hast aber nur {$availableBaxx} verfügbar." : null));
@endphp
<details class="rounded-lg border border-base-300 p-3" data-testid="rpg-character-rename" @if($renameFailed) open @endif>
    <summary class="cursor-pointer font-medium" aria-label="{{ $character->displayName() }} umbenennen für {{ $renameCost }} Baxx">Umbenennen · {{ $renameCost }} Baxx</summary>
    <form method="POST" action="{{ route('rpg.characters.rename', $character) }}" class="mt-4 space-y-3 max-w-lg">
        @csrf
        @method('PATCH')
        <input type="hidden" name="revision" value="{{ $character->revision }}">
        <input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <p class="break-words whitespace-normal">Bisheriger Name: <strong>{{ $character->displayName() }}</strong></p>
        <label for="rename-name-{{ $character->id }}" class="block font-medium">Neuer Charaktername</label>
        <input id="rename-name-{{ $character->id }}" name="character_name" value="{{ $enteredName }}" class="input input-bordered w-full"
            required maxlength="255" aria-describedby="rename-help-{{ $character->id }}{{ $renameError ? ' rename-error-'.$character->id : '' }}"
            @if($renameError) aria-invalid="true" autofocus @endif>
        <p id="rename-help-{{ $character->id }}" class="text-sm whitespace-normal">Jede Namensänderung kostet {{ $renameCost }} Baxx. Maximal 255 Zeichen.</p>
        @if($renameError)
            <p id="rename-error-{{ $character->id }}" role="alert" class="text-error whitespace-normal">{{ $renameError }}</p>
        @endif
        @if($blockedReason)
            <p class="whitespace-normal" data-testid="rpg-character-rename-blocked">{{ $blockedReason }}</p>
        @else
            <p>Verfügbar: {{ $availableBaxx }} Baxx · Danach: {{ $availableBaxx - $renameCost }} Baxx</p>
        @endif
        @if(in_array($character->id, $charactersWithInvitations, true))
            <p class="whitespace-normal">Für diesen Charakter gibt es offene Kampfeinladungen. Nach der Umbenennung müssen diese neu erstellt werden.</p>
        @endif
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary btn-sm" @disabled($blockedReason !== null)>Für {{ $renameCost }} Baxx umbenennen</button>
            <a href="{{ route('rpg.characters.index') }}" class="btn btn-ghost btn-sm">Abbrechen</a>
        </div>
    </form>
</details>

<!-- resources/views/profile/update-seriendaten-form.blade.php -->
<div>
    <x-header title="{{ __('Serienspezifische Daten') }}" subtitle="{{ __('Hier kannst du deine Lieblingsdetails zur Serie Maddrax hinterlegen. Alle Felder sind optional. Alle Angaben, die du hier machst, können von anderen Mitgliedern eingesehen werden. Die Auswahlmöglichkeiten werden freundlicherweise durch das Maddraxikon zur Verfügung gestellt. Sollte eine Auswahl fehlen, liegt das daran, dass die entsprechenden Informationen im Maddraxikon noch fehlen.') }}" size="text-lg" class="!mb-4" />

    <x-form wire:submit="updateSeriendaten" class="max-w-xl">
        @php
            $romanOptions = collect($romane)->map(fn($r) => ['id' => $r, 'name' => $r])->toArray();
            $autorOptions = collect($autoren)->map(fn($a) => ['id' => $a, 'name' => $a])->toArray();
            $hardcoverOptions = collect($hardcover)->map(fn($h) => ['id' => $h, 'name' => $h])->toArray();
            $coverOptions = collect($covers)->map(fn($c) => ['id' => $c, 'name' => $c])->toArray();
            $zyklusOptions = collect($zyklen)->map(fn($z) => ['id' => $z, 'name' => $z . '-Zyklus'])->toArray();
            $figurOptions = collect($figuren)->map(fn($f) => ['id' => $f, 'name' => $f])->toArray();
            $schauplatzOptions = collect($schauplaetze)->map(fn($s) => ['id' => $s, 'name' => $s])->toArray();
            $themaOptions = collect($schlagworte)->map(fn($t) => ['id' => $t, 'name' => $t])->toArray();
        @endphp

        <!-- Einstiegsroman Dropdown -->
        <x-select
            id="einstiegsroman"
            label="{{ __('Einstiegsroman (optional)') }}"
            wire:model="state.einstiegsroman"
            :options="$romanOptions"
            placeholder="Roman auswählen" />

        <!-- Lesestand Dropdown -->
        <x-select
            id="lesestand"
            label="{{ __('Aktueller Lesestand (optional)') }}"
            wire:model="state.lesestand"
            :options="$romanOptions"
            placeholder="Roman auswählen" />

        <!-- Lieblingsautor Dropdown -->
        <x-select
            id="lieblingsautor"
            label="{{ __('Lieblingsautor:in (optional)') }}"
            wire:model="state.lieblingsautor"
            :options="$autorOptions"
            placeholder="Autor:in auswählen" />

        <!-- Lieblingsroman Dropdown -->
        <x-select
            id="lieblingsroman"
            label="{{ __('Lieblingsroman (optional)') }}"
            wire:model="state.lieblingsroman"
            :options="$romanOptions"
            placeholder="Roman auswählen" />

        <!-- Lieblingshardcover Dropdown -->
        <x-select
            id="lieblingshardcover"
            label="{{ __('Lieblingshardcover (optional)') }}"
            wire:model="state.lieblingshardcover"
            :options="$hardcoverOptions"
            placeholder="Hardcover auswählen" />

        <!-- Lieblingscover Dropdown -->
        <x-select
            id="lieblingscover"
            label="{{ __('Lieblingscover (optional)') }}"
            wire:model="state.lieblingscover"
            :options="$coverOptions"
            placeholder="Cover auswählen" />

        <!-- Lieblingszyklus Dropdown -->
        <x-select
            id="lieblingszyklus"
            label="{{ __('Lieblingszyklus (optional)') }}"
            wire:model="state.lieblingszyklus"
            :options="$zyklusOptions"
            placeholder="Zyklus auswählen" />

        <!-- Lieblingsfigur Dropdown -->
        <x-select
            id="lieblingsfigur"
            label="{{ __('Lieblingsfigur (optional)') }}"
            wire:model="state.lieblingsfigur"
            :options="$figurOptions"
            placeholder="Figur auswählen" />

        <!-- Lieblingsschauplatz Dropdown -->
        <x-select
            id="lieblingsschauplatz"
            label="{{ __('Lieblingsschauplatz (optional)') }}"
            wire:model="state.lieblingsschauplatz"
            :options="$schauplatzOptions"
            placeholder="Schauplatz auswählen" />

        <!-- Lieblingsthema Dropdown -->
        <x-select
            id="lieblingsthema"
            label="{{ __('Lieblingsthema (optional)') }}"
            wire:model="state.lieblingsthema"
            :options="$themaOptions"
            placeholder="Thema auswählen" />

        <!-- TODO: Lieblingsmutation -->

        <x-slot:actions>
            <x-button id="saveButton" type="submit" class="btn-primary">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>
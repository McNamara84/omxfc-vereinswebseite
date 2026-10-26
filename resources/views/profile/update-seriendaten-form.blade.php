<div data-profile-series-form>
    <p class="mb-4 text-sm" aria-live="polite" data-profile-series-completion>
        {{ $completion['filled'] }} von {{ $completion['total'] }} Angaben gespeichert.
        Die Angaben sind freiwillig. Für den Abschluss der Profilpflege-Tour müssen alle {{ $completion['total'] }} Felder ausgefüllt und gespeichert sein.
    </p>
    @if ($unavailable !== [])
        <p class="mb-4 text-sm text-warning" role="status">Einige Auswahllisten sind derzeit nicht verfügbar: {{ implode(', ', $unavailable) }}. Vorhandene Angaben bleiben erhalten. Bitte versuche es später erneut.</p>
    @endif
    <x-form wire:submit="updateSeriendaten" class="max-w-xl" data-profile-series-fields>
        @foreach ($fields as $field => $label)
            <div data-tour-profile-key="profile-field-{{ $field }}" wire:key="series-field-{{ $field }}">
                @if ($field === 'lieblingsmutation')
                    <x-input id="{{ $field }}" label="{{ $label }} (optional)" wire:model="state.{{ $field }}" maxlength="255" hint="Trage den konkreten Namen deiner Lieblingsmutation ein." />
                @else
                    @php
                        $options = collect($fieldOptions[$field])->map(fn ($value) => ['id' => $value, 'name' => $field === 'lieblingszyklus' ? $value . '-Zyklus' : $value])->all();
                    @endphp
                    <x-select id="{{ $field }}" label="{{ $label }} (optional)" wire:model="state.{{ $field }}" :options="$options" placeholder="Bitte auswählen" />
                @endif
                @error($field)
                    <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <x-slot:actions>
            <x-button id="saveButton" type="submit" class="btn-primary" data-tour-profile-key="profile-series-save" wire:loading.attr="disabled" wire:target="updateSeriendaten">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>

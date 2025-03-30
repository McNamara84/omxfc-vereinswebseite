<x-form-section submit="updateSeriendaten">
    <x-slot name="title">
        {{ __('Serienspezifische Daten') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Hier kannst du deine Lieblingsdetails zur Serie Maddrax hinterlegen. Alle Felder sind optional. Alle Angaben, die du hier machst, können von anderen Mitgliedern eingesehen werden.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Lieblingsautor Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsautor" value="{{ __('Lieblingsautor:in') }}" />
            <select id="lieblingsautor" wire:model="state.lieblingsautor" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Bitte auswählen (optional)</option>
                @foreach($autoren as $autor)
                    <option value="{{ $autor }}">{{ $autor }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsautor" class="mt-2" />
</div>
        @foreach ([
            'einstiegsroman' => 'Einstiegsroman',
            'lesestand' => 'Aktueller Lesestand',
            'lieblingsroman' => 'Lieblingsroman',
            'lieblingsfigur' => 'Lieblingsfigur',
            'lieblingsmutation' => 'Lieblingsmutation',
            'lieblingsschauplatz' => 'Lieblingsschauplatz',
            'lieblingszyklus' => 'Lieblingszyklus',
        ] as $field => $label)
            <div class="col-span-6 sm:col-span-4">
                <x-label for="{{ $field }}" :value="$label" />
                <x-input id="{{ $field }}" type="text" class="mt-1 block w-full"
                         wire:model.defer="state.{{ $field }}" autocomplete="off" />
                <x-input-error for="{{ $field }}" class="mt-2" />
            </div>
        @endforeach
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Gespeichert.') }}
        </x-action-message>

        <x-button>
            {{ __('Speichern') }}
        </x-button>
    </x-slot>
</x-form-section>

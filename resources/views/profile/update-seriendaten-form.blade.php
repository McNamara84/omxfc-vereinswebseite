<x-form-section submit="updateSeriendaten">
    <x-slot name="title">
        {{ __('Serienspezifische Daten') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Hier kannst du deine Lieblingsdetails zur Serie Maddrax hinterlegen. Alle Felder sind optional. Alle Angaben, die du hier machst, können von anderen Mitgliedern eingesehen werden. Die Auswahlmöglichkeiten werden freundlicherweise durch das Maddraxikon zur Verfügung gestellt. Sollte eine Auswahl fehlen, liegt das daran, dass die entsprechenden Informationen im Maddraxikon noch fehlen.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Einstiegsroman Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="einstiegsroman" value="{{ __('Einstiegsroman (optional)') }}" />
            <select id="einstiegsroman" wire:model="state.einstiegsroman" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Roman auswählen</option>
                @foreach($romane as $roman)
                    <option value="{{ $roman }}">{{ $roman }}</option>
                @endforeach
            </select>
            <x-input-error for="einstiegsroman" class="mt-2" />
        </div>
        <!-- Lesestand Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lesestand" value="{{ __('Aktueller Lesestand (optional)') }}" />
            <select id="lesestand" wire:model="state.lesestand" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Roman auswählen</option>
                @foreach($romane as $roman)
                    <option value="{{ $roman }}">{{ $roman }}</option>
                @endforeach
            </select>
            <x-input-error for="lesestand" class="mt-2" />
        </div>
        <!-- Lieblingsautor Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsautor" value="{{ __('Lieblingsautor:in (optional)') }}" />
            <select id="lieblingsautor" wire:model="state.lieblingsautor" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Autor:in auswählen</option>
                @foreach($autoren as $autor)
                    <option value="{{ $autor }}">{{ $autor }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsautor" class="mt-2" />
        </div>
        <!-- Lieblingsroman Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsroman" value="{{ __('Lieblingsroman (optional)') }}" />
            <select id="lieblingsroman" wire:model="state.lieblingsroman" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Roman auswählen</option>
                @foreach($romane as $roman)
                    <option value="{{ $roman }}">{{ $roman }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsroman" class="mt-2" />
        </div>
        <!-- Lieblingszyklus Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingszyklus" value="{{ __('Lieblingszyklus (optional)') }}" />
            <select id="lieblingszyklus" wire:model="state.lieblingszyklus" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Zyklus auswählen</option>
                @foreach($zyklen as $zyklus)
                    <option value="{{ $zyklus }}">{{ $zyklus }}-Zyklus</option>
                @endforeach
            </select>
            <x-input-error for="lieblingszyklus" class="mt-2" />
        </div>
        <!-- Lieblingsfigur Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsfigur" value="{{ __('Lieblingsfigur (optional)') }}" />
            <select id="lieblingsfigur" wire:model="state.lieblingsfigur" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Figur auswählen</option>
                @foreach($figuren as $figur)
                    <option value="{{ $figur }}">{{ $figur }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsfigur" class="mt-2" />
        </div>
        @foreach ([
            'lieblingsmutation' => 'Lieblingsmutation',
            'lieblingsschauplatz' => 'Lieblingsschauplatz',
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

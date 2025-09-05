<!-- resources/views/profile/update-seriendaten-form.blade.php -->
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
            <select id="einstiegsroman" wire:model="state.einstiegsroman"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
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
            <select id="lesestand" wire:model="state.lesestand"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
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
            <select id="lieblingsautor" wire:model="state.lieblingsautor"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
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
            <select id="lieblingsroman" wire:model="state.lieblingsroman"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Roman auswählen</option>
                @foreach($romane as $roman)
                    <option value="{{ $roman }}">{{ $roman }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsroman" class="mt-2" />
        </div>
        <!-- Lieblingshardcover Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingshardcover" value="{{ __('Lieblingshardcover (optional)') }}" />
            <select id="lieblingshardcover" wire:model="state.lieblingshardcover"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Hardcover auswählen</option>
                @foreach($hardcover as $hc)
                    <option value="{{ $hc }}">{{ $hc }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingshardcover" class="mt-2" />
        </div>
        <!-- Lieblingscover Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingscover" value="{{ __('Lieblingscover (optional)') }}" />
            <select id="lieblingscover" wire:model="state.lieblingscover"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Cover auswählen</option>
                @foreach($covers as $cover)
                    <option value="{{ $cover }}">{{ $cover }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingscover" class="mt-2" />
        </div>
        <!-- Lieblingszyklus Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingszyklus" value="{{ __('Lieblingszyklus (optional)') }}" />
            <select id="lieblingszyklus" wire:model="state.lieblingszyklus"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Zyklus auswählen</option>
                @foreach($zyklen as $zyklus)
                    <option value="{{ $zyklus }}">{{ $zyklus }}-Zyklus</option>
                @endforeach
            </select>
            <x-input-error for="lieblingszyklus" class="mt-2" />
        </div>
        <!-- FALLBACK: Lieblingsfigur als normales Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsfigur" value="{{ __('Lieblingsfigur (optional)') }}" />
            <select id="lieblingsfigur" wire:model="state.lieblingsfigur"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Figur auswählen</option>
                @foreach($figuren as $figur)
                    <option value="{{ $figur }}">{{ $figur }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsfigur" class="mt-2" />
        </div>

        <!-- FALLBACK: Lieblingsschauplatz als normales Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsschauplatz" value="{{ __('Lieblingsschauplatz (optional)') }}" />
            <select id="lieblingsschauplatz" wire:model="state.lieblingsschauplatz"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Schauplatz auswählen</option>
                @foreach($schauplaetze as $schauplatz)
                    <option value="{{ $schauplatz }}">{{ $schauplatz }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsschauplatz" class="mt-2" />
        </div>
        <!-- Lieblingsthema Dropdown -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="lieblingsthema" value="{{ __('Lieblingsthema (optional)') }}" />
            <select id="lieblingsthema" wire:model="state.lieblingsthema"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                <option value="">Thema auswählen</option>
                @foreach($schlagworte as $thema)
                    <option value="{{ $thema }}">{{ $thema }}</option>
                @endforeach
            </select>
            <x-input-error for="lieblingsthema" class="mt-2" />
        </div>
        <!-- TODO: Lieblingsmutation -->
    </x-slot>
    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Gespeichert.') }}
        </x-action-message>
        <x-button id="saveButton">
            {{ __('Speichern') }}
        </x-button>
    </x-slot>
</x-form-section>
<script>
    function autocomplete(items, initialValue = '', fieldName = '') {
        return {
            open: false,
            items: items,
            filteredItems: [],
            fieldName: fieldName,
            displayValue: initialValue || '',

            init() {
                // Initialen Wert setzen falls vorhanden
                if (initialValue) {
                    this.displayValue = initialValue;
                }

                // Überwache Änderungen des Input-Feldes von Livewire
                this.$watch('displayValue', (value) => {
                    this.syncWithLivewire(value);
                });
            },

            handleFocus() {
                // Nur öffnen und filtern wenn bereits Text vorhanden ist
                if (this.displayValue.length > 0) {
                    this.filterItems();
                    this.open = true;
                }
            },

            handleInput() {
                this.filterItems();
                this.syncWithLivewire(this.displayValue);

                // Nur öffnen wenn mindestens ein Zeichen eingegeben wurde
                if (this.displayValue.length > 0) {
                    this.open = true;
                } else {
                    this.open = false;
                }
            },

            filterItems() {
                if (!this.displayValue || this.displayValue.length === 0) {
                    this.filteredItems = [];
                    return;
                }

                const queryLower = this.displayValue.toLowerCase();
                this.filteredItems = this.items.filter(item =>
                    item.toLowerCase().includes(queryLower)
                ).slice(0, 10);
            },

            selectItem(item) {
                // Wert setzen
                this.displayValue = item;

                // Mit Livewire synchronisieren
                this.syncWithLivewire(item);

                // Auswahlmenü schließen
                this.open = false;
            },

            syncWithLivewire(value) {
                // Mehrere Synchronisations-Methoden versuchen

                // Methode 1: $wire verwenden falls verfügbar
                if (typeof this.$wire !== 'undefined' && this.$wire.set) {
                    try {
                        this.$wire.set('state.' + this.fieldName, value);
                    } catch (e) {
                        console.log('Livewire $wire.set failed:', e);
                    }
                }

                // Methode 2: Das Input-Element direkt manipulieren
                const inputElement = this.$refs[this.fieldName + 'Input'];
                if (inputElement) {
                    inputElement.value = value;

                    // Input Event auslösen
                    inputElement.dispatchEvent(new Event('input', { bubbles: true }));

                    // Change Event auslösen
                    inputElement.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Methode 3: Livewire direkt über window.Livewire ansprechen
                if (typeof window.Livewire !== 'undefined') {
                    try {
                        // Den Livewire Component finden und das Feld setzen
                        const component = window.Livewire.find(inputElement.getAttribute('wire:id') || this.getComponentId());
                        if (component && component.set) {
                            component.set('state.' + this.fieldName, value);
                        }
                    } catch (e) {
                        console.log('Livewire window.Livewire failed:', e);
                    }
                }
            },

            getComponentId() {
                // Versuche die Component ID zu finden
                let element = this.$el;
                while (element && !element.hasAttribute('wire:id')) {
                    element = element.parentElement;
                }
                return element ? element.getAttribute('wire:id') : null;
            }
        };
    }
</script>
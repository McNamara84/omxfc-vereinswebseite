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
        <div class="col-span-6 sm:col-span-4" x-data="autocomplete(@js($figuren), '{{ $state['lieblingsfigur'] }}')">
            <x-label for="lieblingsfigur" value="{{ __('Lieblingsfigur (optional)') }}" />
        
            <input type="text"
                id="lieblingsfigur"
                wire:model="state.lieblingsfigur"
                autocomplete="off"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm"
                x-model="query"
                @input="filterItems()"
                @focus="open = true"
                @click.away="open = false">
        
            <ul x-show="open && filteredItems.length" class="mt-1 bg-white dark:bg-gray-700 shadow-lg rounded-md max-h-60 overflow-auto z-50 border">
                <template x-for="item in filteredItems" :key="item">
                    <li class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer"
                        @click="selectItem(item)">
                        <span x-text="item"></span>
                    </li>
                </template>
            </ul>
            <x-input-error for="lieblingsfigur" class="mt-2" />
        </div>
        <!-- Lieblingsschauplatz Dropdown -->
        <div class="col-span-6 sm:col-span-4" x-data="autocomplete(@js($schauplaetze), '{{ $state['lieblingsschauplatz'] }}')">
            <x-label for="lieblingsschauplatz" value="{{ __('Lieblingsschauplatz (optional)') }}" />
            <input type="text"
                id="lieblingsschauplatz"
                wire:model="state.lieblingsschauplatz"
                autocomplete="off"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm"
                x-model="query"
                @input="filterItems()"
                @focus="open = true"
                @click.away="open = false">
            <ul x-show="open && filteredItems.length" class="mt-1 bg-white dark:bg-gray-700 shadow-lg rounded-md max-h-60 overflow-auto z-50 border">
                <template x-for="item in filteredItems" :key="item">
                    <li class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer"
                        @click="selectItem(item)">
                        <span x-text="item"></span>
                    </li>
                </template>
            </ul>
            <x-input-error for="lieblingsschauplatz" class="mt-2" />
        </div>
        <!-- TODO: Lieblingsmutation -->
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
<script>
    function autocomplete(items, initialValue = '') {
        return {
            query: initialValue || '',
            open: false,
            items: items,
            filteredItems: [],
            filterItems() {
                this.open = true;
                const queryLower = this.query.toLowerCase();
                this.filteredItems = this.items.filter(item => item.toLowerCase().includes(queryLower)).slice(0, 10);
            },
            selectItem(item) {
                this.query = item;
                this.$wire.set('state.' + this.$el.querySelector('input').id, item);
                this.open = false;
            }
        };
    }
    </script>

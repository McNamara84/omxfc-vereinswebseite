// Fantreffen Anmeldeformular - T-Shirt Dropdown Toggle
// Hinweis: maryUI-Komponenten (<x-checkbox>, <x-form-select>) generieren
// eigene IDs ("mary" + md5 + übergebene id). Daher werden hier
// name-Attribute statt IDs für die Selektion verwendet.
//
// Die Initialisierung muss sowohl bei vollem Seitenlade (DOMContentLoaded)
// als auch nach SPA-Navigation via Livewire/Alpine (livewire:navigated)
// erfolgen, da wire:navigate kein neues DOMContentLoaded auslöst.
function initFantreffenTshirtToggle() {
    const checkbox = document.querySelector('input[name="tshirt_bestellt"]');
    const container = document.getElementById('tshirt-groesse-container');
    const select = document.querySelector('select[name="tshirt_groesse"]');

    if (!checkbox || !container || !select) {
        return; // Nicht auf dieser Seite
    }

    // Verhindere doppelte Listener bei erneuter Initialisierung
    if (checkbox.dataset.tshirtToggleInitialized) {
        return;
    }
    checkbox.dataset.tshirtToggleInitialized = 'true';

    // Funktion zum Ein-/Ausblenden des Dropdowns
    const toggleDropdown = () => {
        if (checkbox.checked) {
            container.classList.remove('hidden');
            select.required = true;
        } else {
            container.classList.add('hidden');
            select.required = false;
            select.value = ''; // Auswahl zurücksetzen
        }
    };

    // Initial state (falls bereits gecheckt, z.B. bei Validierungsfehler)
    toggleDropdown();

    // Event Listener
    checkbox.addEventListener('change', toggleDropdown);
}

document.addEventListener('DOMContentLoaded', initFantreffenTshirtToggle);
document.addEventListener('livewire:navigated', initFantreffenTshirtToggle);

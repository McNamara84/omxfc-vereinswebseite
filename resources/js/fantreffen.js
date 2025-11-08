// Fantreffen Anmeldeformular - T-Shirt Dropdown Toggle
document.addEventListener('DOMContentLoaded', () => {
    const checkbox = document.getElementById('tshirt_bestellt');
    const container = document.getElementById('tshirt-groesse-container');
    const select = document.getElementById('tshirt_groesse');
    
    if (!checkbox || !container || !select) {
        return; // Nicht auf dieser Seite
    }
    
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
});

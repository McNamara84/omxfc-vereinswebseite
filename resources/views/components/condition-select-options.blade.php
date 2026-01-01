{{--
    Condition Select Options Component
    
    Rendert die <option>-Elemente für Zustandsauswahl in der Romantauschbörse.
    
    Props:
    - $selected: Der aktuell ausgewählte Wert
    - $includeEmpty: Ob eine leere "Gleicher Zustand" Option angezeigt werden soll (für condition_max)
    - $includeWorst: Ob die schlechtesten Zustände (Z3-4, Z4) angezeigt werden sollen
--}}
@props([
    'selected' => '',
    'includeEmpty' => false,
    'includeWorst' => false,
])

@if($includeEmpty)
    <option value="" @selected($selected === '')>— Gleicher Zustand —</option>
@endif
<option value="Z0" @selected($selected === 'Z0')>Z0 - Druckfrisch</option>
<option value="Z0-1" @selected($selected === 'Z0-1')>Z0-1</option>
<option value="Z1" @selected($selected === 'Z1')>Z1 - Sehr gut</option>
<option value="Z1-2" @selected($selected === 'Z1-2')>Z1-2</option>
<option value="Z2" @selected($selected === 'Z2')>Z2 - Gut</option>
<option value="Z2-3" @selected($selected === 'Z2-3')>Z2-3</option>
<option value="Z3" @selected($selected === 'Z3')>Z3 - Gebraucht</option>
@if($includeWorst)
    <option value="Z3-4" @selected($selected === 'Z3-4')>Z3-4</option>
    <option value="Z4" @selected($selected === 'Z4')>Z4 - Schlecht</option>
@endif

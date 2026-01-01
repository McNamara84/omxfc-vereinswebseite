{{--
    Condition Select Options Component
    
    Rendert die <option>-Elemente für Zustandsauswahl in der Romantauschbörse.
    
    Props:
    - $selected: Der aktuell ausgewählte Wert
    - $includeEmpty: Ob eine leere "Gleicher Zustand" Option angezeigt werden soll (für condition_max)
    - $includeWorst: Ob die schlechtesten Zustände (Z3-4, Z4) angezeigt werden sollen
    
    Zustandsskala (Z0 = bester, Z4 = schlechtester):
    - Ganzzahlige Werte (Z0, Z1, Z2, Z3, Z4) beschreiben eindeutige Zustände
    - Zwischenwerte (Z0-1, Z1-2, Z2-3, Z3-4) beschreiben Zustand "zwischen" zwei Stufen
      z.B. Z1-2 = besser als Z2 aber nicht ganz Z1
    
    Barrierefreiheit:
    - Zwischenwerte haben beschreibenden Text im Label für Screen-Reader
    - title-Attribute dienen als zusätzliche Tooltips für Maus-Nutzer
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
<option value="Z0-1" @selected($selected === 'Z0-1') title="Zwischen Z0 und Z1 – fast druckfrisch">Z0-1 - Fast druckfrisch</option>
<option value="Z1" @selected($selected === 'Z1')>Z1 - Sehr gut</option>
<option value="Z1-2" @selected($selected === 'Z1-2') title="Zwischen Z1 und Z2 – sehr gut mit leichten Mängeln">Z1-2 - Sehr gut mit leichten Mängeln</option>
<option value="Z2" @selected($selected === 'Z2')>Z2 - Gut</option>
<option value="Z2-3" @selected($selected === 'Z2-3') title="Zwischen Z2 und Z3 – gut mit deutlichen Gebrauchsspuren">Z2-3 - Gut mit Gebrauchsspuren</option>
<option value="Z3" @selected($selected === 'Z3')>Z3 - Gebraucht</option>
@if($includeWorst)
    <option value="Z3-4" @selected($selected === 'Z3-4') title="Zwischen Z3 und Z4 – stark gebraucht">Z3-4 - Stark gebraucht</option>
    <option value="Z4" @selected($selected === 'Z4')>Z4 - Schlecht</option>
@endif

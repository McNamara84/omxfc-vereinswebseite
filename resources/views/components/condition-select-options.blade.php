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
    - Zwischenwerte haben title-Attribute als Tooltips für Maus-Nutzer
    - HINWEIS ZU REDUNDANZ: Die title-Attribute wiederholen z.T. Informationen aus dem
      Options-Text. Dies ist bewusst so gestaltet:
      - Screen-Reader lesen primär den Options-Text vor
      - title dient als zusätzlicher visueller Tooltip für Maus-Nutzer
      - Bei einigen Screen-Readern wird title ignoriert, daher ist die Info im Text wichtiger
      - Falls die Verbosität problematisch ist, können die title-Attribute entfernt werden
    
    Übersetzungen: lang/de/romantausch.php → condition.*
--}}
@props([
    'selected' => '',
    'includeEmpty' => false,
    'includeWorst' => false,
])

@if($includeEmpty)
    <option value="" @selected($selected === '')>{{ __('romantausch.condition.same') }}</option>
@endif
<option value="Z0" @selected($selected === 'Z0')>Z0 - {{ __('romantausch.condition.Z0') }}</option>
<option value="Z0-1" @selected($selected === 'Z0-1') title="{{ __('romantausch.condition.Z0-1_title') }}">Z0-1 - {{ __('romantausch.condition.Z0-1') }}</option>
<option value="Z1" @selected($selected === 'Z1')>Z1 - {{ __('romantausch.condition.Z1') }}</option>
<option value="Z1-2" @selected($selected === 'Z1-2') title="{{ __('romantausch.condition.Z1-2_title') }}">Z1-2 - {{ __('romantausch.condition.Z1-2') }}</option>
<option value="Z2" @selected($selected === 'Z2')>Z2 - {{ __('romantausch.condition.Z2') }}</option>
<option value="Z2-3" @selected($selected === 'Z2-3') title="{{ __('romantausch.condition.Z2-3_title') }}">Z2-3 - {{ __('romantausch.condition.Z2-3') }}</option>
<option value="Z3" @selected($selected === 'Z3')>Z3 - {{ __('romantausch.condition.Z3') }}</option>
@if($includeWorst)
    <option value="Z3-4" @selected($selected === 'Z3-4') title="{{ __('romantausch.condition.Z3-4_title') }}">Z3-4 - {{ __('romantausch.condition.Z3-4') }}</option>
    <option value="Z4" @selected($selected === 'Z4')>Z4 - {{ __('romantausch.condition.Z4') }}</option>
@endif

{{--
    Form Select Component – Server-seitig gerendert mit <option selected>

    Drop-in-Ersatz für maryUI's <x-select> in nicht-Livewire-Formularen.
    MaryUI's <x-select> setzt KEIN selected-Attribut auf <option>-Elemente
    und benötigt daher Livewire/wire:model für die Wert-Bindung.

    Diese Komponente repliziert maryUIs HTML-Struktur (fieldset + legend + label.select)
    exakt, rendert aber den ausgewählten Wert korrekt serverseitig via @selected().

    @props
    - label: Sichtbares Label (wird als <legend> gerendert)
    - options: Array von ['id' => ..., 'name' => ...] (oder custom keys via optionValue/optionLabel)
    - value: Aktuell ausgewählter Wert (z.B. old('field', $default))
    - placeholder: Optionaler Platzhalter-Text als erste Option
    - placeholderValue: Wert der Platzhalter-Option (Standard: '')
    - optionValue: Schlüssel für den Optionswert (Standard: 'id')
    - optionLabel: Schlüssel für den Optionstext (Standard: 'name')
    - errorField: Feldname für Fehleranzeige (Standard: name-Attribut)
    - hint: Optionaler Hinweistext unter dem Select

    Alle weiteren Attribute (id, name, aria-label, required, etc.) werden
    an das <select>-Element durchgereicht. class-Attribute werden auf den
    select-Wrapper angewendet (wie bei maryUI).

    @see vendor/robsontenorio/mary/src/View/Components/Select.php
--}}
@props([
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'placeholderValue' => '',
    'optionValue' => 'id',
    'optionLabel' => 'name',
    'errorField' => null,
    'hint' => null,
])

@php
    $errorFieldName = $errorField ?? $attributes->get('name');
    $hasError = $errors->has($errorFieldName);
@endphp

<div>
    <fieldset class="fieldset py-0">
        @if($label)
            <legend class="fieldset-legend mb-0.5">
                {{ $label }}

                @if($attributes->has('required'))
                    <span class="text-error">*</span>
                @endif
            </legend>
        @endif

        <label>
            <div class="w-full">
                <label
                    {{
                        $attributes->whereStartsWith('class')->class([
                            'select w-full',
                            '!select-error' => $hasError,
                        ])
                    }}
                >
                    <select {{ $attributes->whereDoesntStartWith('class') }}>
                        @if($placeholder)
                            <option
                                value="{{ $placeholderValue }}"
                                @selected($value === null || (string) $value === (string) $placeholderValue)
                            >{{ $placeholder }}</option>
                        @endif

                        @foreach($options as $option)
                            <option
                                value="{{ data_get($option, $optionValue) }}"
                                @selected((string) data_get($option, $optionValue) === (string) $value)
                                @if(data_get($option, 'disabled')) disabled @endif
                            >{{ data_get($option, $optionLabel) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </label>

        {{-- ERROR --}}
        @if($hasError)
            @foreach($errors->get($errorFieldName) as $message)
                @foreach(\Illuminate\Support\Arr::wrap($message) as $line)
                    <div class="text-error">{{ $line }}</div>
                @endforeach
            @endforeach
        @endif

        {{-- HINT --}}
        @if($hint)
            <div class="fieldset-label">{{ $hint }}</div>
        @endif
    </fieldset>
</div>

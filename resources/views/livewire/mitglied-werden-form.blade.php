<form wire:submit="submit" class="w-full" data-testid="mitglied-werden-form">
    @if($errors->any())
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded" role="alert">
            Bitte korrigiere die markierten Felder.
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
        <x-input wire:model.blur="vorname" name="vorname" label="Vorname" required class="w-full" autocomplete="given-name" />

        <x-input wire:model.blur="nachname" name="nachname" label="Nachname" required class="w-full" autocomplete="family-name" />

        <x-input wire:model.blur="strasse" name="strasse" label="Straße" required class="w-full" autocomplete="address-line1" />

        <x-input wire:model.blur="hausnummer" name="hausnummer" label="Hausnummer" required class="w-full" autocomplete="address-line2" />

        <x-input wire:model.blur="plz" name="plz" label="Postleitzahl" required class="w-full" autocomplete="postal-code" />

        <x-input wire:model.blur="stadt" name="stadt" label="Stadt" required class="w-full" autocomplete="address-level2" />

        @php
            $landOptions = [
                ['id' => 'Deutschland', 'name' => 'Deutschland'],
                ['id' => 'Österreich', 'name' => 'Österreich'],
                ['id' => 'Schweiz', 'name' => 'Schweiz'],
            ];
        @endphp
        <x-form-select
            wire:model.blur="land"
            name="land"
            label="Land"
            aria-label="Land"
            class="w-full"
            placeholder="Bitte wählen"
            :options="$landOptions"
            required
        />

        <x-input wire:model.blur="mail" name="mail" label="Mailadresse" type="email" required class="w-full" autocomplete="username" />

        <x-input wire:model.blur="passwort" name="passwort" label="Passwort" type="password" required class="w-full" autocomplete="new-password" hint="Mindestens 6 Zeichen." />

        <x-input wire:model.blur="passwort_confirmation" name="passwort_confirmation" label="Passwort wiederholen" type="password" required class="w-full" autocomplete="new-password" hint="Bitte wiederhole dein Passwort." />

        <div class="col-span-1 md:col-span-2 w-full space-y-2">
            <label for="mitgliedsbeitrag" class="pt-0 label label-text font-semibold">
                Jährlicher Mitgliedsbeitrag: <span id="beitrag-output" class="font-semibold text-primary" aria-live="polite">{{ $mitgliedsbeitrag }}€</span>
            </label>
            <input
                type="range"
                id="mitgliedsbeitrag"
                wire:model.live="mitgliedsbeitrag"
                name="mitgliedsbeitrag"
                min="12"
                max="120"
                step="1"
                class="range range-primary w-full"
            >
            <p class="text-sm text-base-content/80">Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende dich gerne an den Vorstand.</p>
        </div>

        <x-input wire:model.blur="telefon" name="telefon" label="Handynummer (optional)" type="tel" class="w-full" autocomplete="tel" placeholder="+49 170 1234567" hint="Optional. Bitte im internationalen Format eingeben." />

        @php
            $vereinGefundenOptions = [
                ['id' => 'Facebook', 'name' => 'Facebook'],
                ['id' => 'Instagram', 'name' => 'Instagram'],
                ['id' => 'Leserkontaktseite', 'name' => 'Leserkontaktseite'],
                ['id' => 'Befreundete Person', 'name' => 'Befreundete Person'],
                ['id' => 'Fantreffen/MaddraxCon', 'name' => 'Fantreffen/MaddraxCon'],
                ['id' => 'Google', 'name' => 'Google'],
                ['id' => 'Sonstiges', 'name' => 'Sonstiges'],
            ];
        @endphp
        <x-form-select
            wire:model.blur="verein_gefunden"
            name="verein_gefunden"
            label="Wie hast du von uns erfahren? (optional)"
            aria-label="Wie hast du von uns erfahren?"
            class="w-full"
            placeholder="Bitte auswählen"
            :options="$vereinGefundenOptions"
        />

        <!-- Checkbox über volle Breite -->
        <div class="col-span-1 md:col-span-2 mt-2">
            <x-checkbox wire:model.live="satzung_check" id="satzung_check" name="satzung_check" data-testid="mitglied-satzung-check">
                <x-slot:label>
                    Ich habe die <a href="{{ route('satzung') }}" target="_blank" rel="noopener noreferrer" class="link link-primary" onclick="event.stopPropagation()">Satzung</a> gelesen und bin mit ihr einverstanden.
                </x-slot:label>
            </x-checkbox>
            @error('satzung_check') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
    </div>

    <button
        type="submit"
        class="btn btn-primary mt-6"
        :class="{ 'opacity-50 cursor-not-allowed': !$wire.satzung_check || $wire.submitting }"
        :disabled="!$wire.satzung_check || $wire.submitting"
        data-testid="mitglied-submit"
    >
        <span wire:loading.remove wire:target="submit">
            <x-icon name="o-paper-airplane" class="h-5 w-5" />
            Antrag absenden
        </span>
        <span wire:loading wire:target="submit" class="flex items-center">
            <x-loading class="loading-spinner loading-md" />
            <span class="ml-2">Dein Antrag wird gesendet, bitte warten...</span>
        </span>
    </button>
</form>

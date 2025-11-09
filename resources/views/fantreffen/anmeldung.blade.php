<x-app-layout
    title="Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V."
    description="Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln mit Signierstunde und Verleihung der Goldenen Taratze."
    :socialImage="asset('build/assets/omxfc-logo-Df-1StAj.png')">
    
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ __('Maddrax-Fantreffen 2026') }}
    </h2>
</x-slot>
<div class="bg-gray-50 dark:bg-gray-900 -mt-8">
    <div class="relative bg-gradient-to-br from-[#8B0116] to-[#6b000e] text-white py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">Maddrax-Fantreffen 2026</h1>
            <div class="flex flex-col sm:flex-row justify-center gap-6 text-lg mb-6">
                <span>📅 Freitag, 9. Mai 2026</span>
                <span>🕖 ab 19:00 Uhr</span>
                <span>📍 L´Osteria Köln Mülheim</span>
            </div>
            <a href="https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5" target="_blank" class="inline-block px-6 py-3 bg-white text-[#8B0116] font-semibold rounded-lg hover:bg-gray-100">📍 Route in Google Maps</a>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Erfolgs- und Fehlermeldungen -->
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Info Box -->
        <div class="mb-8 p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
            <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Programm</h2>
            <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                <li>✍️ <strong>Signierstunde</strong> mit allen anwesenden Autoren</li>
                <li>🏆 Verleihung der <strong>Goldenen Taratze</strong></li>
                <li>🍕 Gemeinsames Essen (jeder zahlt sein Essen selbst)</li>
                <li>🎉 Gemütliches Beisammensein mit anderen Fans</li>
            </ul>
        </div>

        <!-- Anmeldeformular -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">Anmeldung</h2>

            <form method="POST" action="{{ route('fantreffen.2026.store') }}" class="space-y-4" id="fantreffen-form">
                @csrf

                @guest
                    <!-- Vorname -->
                    <div>
                        <label for="vorname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vorname *</label>
                        <input type="text" name="vorname" id="vorname" value="{{ old('vorname') }}" required 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-[#8B0116] focus:ring focus:ring-[#8B0116] focus:ring-opacity-50">
                        @error('vorname')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nachname -->
                    <div>
                        <label for="nachname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nachname *</label>
                        <input type="text" name="nachname" id="nachname" value="{{ old('nachname') }}" required 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-[#8B0116] focus:ring focus:ring-[#8B0116] focus:ring-opacity-50">
                        @error('nachname')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- E-Mail -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">E-Mail-Adresse *</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required 
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-[#8B0116] focus:ring focus:ring-[#8B0116] focus:ring-opacity-50">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="p-4 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            Du bist angemeldet als: <strong>{{ $user->vorname }} {{ $user->nachname }}</strong> ({{ $user->email }})
                        </p>
                    </div>
                @endguest

                <!-- Mobilnummer -->
                <div>
                    <label for="mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mobilnummer (optional)</label>
                    <input type="text" name="mobile" id="mobile" value="{{ old('mobile', $user->mobile ?? '') }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-[#8B0116] focus:ring focus:ring-[#8B0116] focus:ring-opacity-50">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Für Rückfragen am Veranstaltungstag</p>
                    @error('mobile')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- T-Shirt Bestellung -->
                <div class="border-t pt-4">
                    <div>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="tshirt_bestellt" id="tshirt_bestellt" value="1" 
                                   {{ old('tshirt_bestellt') ? 'checked' : '' }}
                                   @if($tshirtDeadlinePassed) disabled @endif
                                   class="w-5 h-5 mt-0.5 text-[#8B0116] rounded border-gray-300 dark:border-gray-600 focus:ring-[#8B0116]">
                            <span class="text-sm">
                                <strong class="text-gray-900 dark:text-gray-100">Ich möchte ein Fantreffen-T-Shirt bestellen (15 €)</strong>
                                @if($tshirtDeadlinePassed)
                                    <span class="block text-red-600 dark:text-red-400 mt-1">
                                        ⚠️ Die Bestellfrist für T-Shirts ist abgelaufen.
                                    </span>
                                @elseif($daysUntilDeadline > 0 && $daysUntilDeadline <= 30)
                                    <span class="block text-orange-600 dark:text-orange-400 mt-1">
                                        ⏰ Noch {{ $daysUntilDeadline }} Tage bis zur Bestellfrist (9. April 2026)
                                    </span>
                                @endif
                            </span>
                        </label>

                        <!-- T-Shirt Größe (wird mit JavaScript ein-/ausgeblendet) -->
                        <div id="tshirt-groesse-container" class="mt-3 hidden">
                            <label for="tshirt_groesse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                T-Shirt Größe *
                            </label>
                            <select name="tshirt_groesse" id="tshirt_groesse" 
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-[#8B0116] focus:ring focus:ring-[#8B0116] focus:ring-opacity-50">
                                <option value="">Bitte wählen...</option>
                                <option value="XS" {{ old('tshirt_groesse') === 'XS' ? 'selected' : '' }}>XS</option>
                                <option value="S" {{ old('tshirt_groesse') === 'S' ? 'selected' : '' }}>S</option>
                                <option value="M" {{ old('tshirt_groesse') === 'M' ? 'selected' : '' }}>M</option>
                                <option value="L" {{ old('tshirt_groesse') === 'L' ? 'selected' : '' }}>L</option>
                                <option value="XL" {{ old('tshirt_groesse') === 'XL' ? 'selected' : '' }}>XL</option>
                                <option value="XXL" {{ old('tshirt_groesse') === 'XXL' ? 'selected' : '' }}>XXL</option>
                                <option value="XXXL" {{ old('tshirt_groesse') === 'XXXL' ? 'selected' : '' }}>XXXL</option>
                            </select>
                            @error('tshirt_groesse')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" 
                            class="w-full px-6 py-3 bg-[#8B0116] text-white font-semibold rounded-lg hover:bg-[#6b000e] focus:outline-none focus:ring-2 focus:ring-[#8B0116] focus:ring-offset-2 transition">
                        Verbindlich anmelden
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    * Pflichtfelder
                </p>
            </form>
        </div>

        <!-- Zahlungshinweis -->
        @if(!$tshirtDeadlinePassed)
        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">💳 Zahlungshinweise</h3>
            <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                <li>• Die Teilnahme am Fantreffen ist <strong>kostenlos</strong></li>
                <li>• T-Shirts kosten <strong>15 €</strong> und müssen bis spätestens <strong>9. April 2026</strong> bestellt werden</li>
                <li>• Nach der Anmeldung erhältst du eine E-Mail mit den Zahlungsinformationen</li>
            </ul>
        </div>
        @endif
    </div>
</div>

@push('scripts')
    @vite(['resources/js/fantreffen.js'])
@endpush
</x-app-layout>

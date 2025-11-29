<x-app-layout 
    :title="'Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V.'"
    :description="'Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln mit Signierstunde und Verleihung der Goldenen Taratze.'"
    :socialImage="asset('build/assets/omxfc-logo-Df-1StAj.png')">
<x-slot name="slot">
<div class="bg-gray-50 dark:bg-gray-900 -mt-8">
    <div class="relative bg-gradient-to-br from-[#8B0116] to-[#6b000e] text-white py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">Maddrax-Fantreffen 2026</h1>
            <div class="flex flex-col sm:flex-row justify-center gap-6 text-lg mb-6">
                <span>📅 Samstag, 9. Mai 2026</span>
                <span>🕖 ab 19:00 Uhr</span>
                <span>📍 L´Osteria Köln Mülheim</span>
            </div>
            <a href="https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5" target="_blank" class="inline-block px-6 py-3 bg-white text-[#8B0116] font-semibold rounded-lg hover:bg-gray-100">📍 Route in Google Maps</a>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border-l-4 border-green-500 rounded">
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif
        <div class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 border-l-4 border-yellow-500 rounded">
            <h3 class="font-bold mb-2">ColoniaCon am selben Wochenende!</h3>
            <p>Am selben Wochenende findet auch die <a href="https://www.coloniacon-tng.de/2026" target="_blank" rel="noopener noreferrer" class="text-yellow-900 dark:text-yellow-100 underline font-semibold hover:text-yellow-700 dark:hover:text-yellow-200">ColoniaCon</a> statt. Der Offizielle MADDRAX Fanclub wird dort ebenfalls mit Programmpunkten vertreten sein.</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-[#8B0116] dark:text-[#ff4b63]">Programm</h2>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <span class="font-bold text-[#8B0116] dark:text-[#ff4b63]">19:00</span>
                            <div>
                                <h3 class="font-semibold">Signierstunde mit Autoren</h3>
                                <p class="text-gray-600 dark:text-gray-300">Triff deine Lieblingsautoren!</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span class="font-bold text-[#8B0116] dark:text-[#ff4b63]">20:00</span>
                            <div>
                                <h3 class="font-semibold">Verleihung Goldene Taratze</h3>
                                <p class="text-gray-600 dark:text-gray-300">Die große Preisverleihung!</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-[#8B0116] dark:text-[#ff4b63]">Kosten</h2>
                    <div class="space-y-3">
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Vereinsmitglieder</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">Teilnahme am Event: <strong class="text-green-600 dark:text-green-400">kostenlos</strong></p>
                        </div>
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Gäste</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">Teilnahme am Event: <strong class="text-blue-600 dark:text-blue-400">5,00 €</strong> Spende erbeten</p>
                        </div>
                        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Event-T-Shirt (optional)</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <strong class="text-purple-600 dark:text-purple-400">25,00 €</strong> Spende
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                Für Gäste zusammen mit Teilnahme: 30,00 €
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden sticky top-4">
                    <div class="bg-gradient-to-r from-[#8B0116] to-[#a01526] px-6 py-4">
                        <h2 class="text-2xl font-bold text-white">Anmeldung</h2>
                    </div>
                    <div class="p-6">
                        @if(isset($errors) && $errors->any())
                            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 rounded">
                                <ul class="text-sm text-red-800 dark:text-red-200 space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        @if(!Auth::check())
                            <div class="mb-4 p-3 bg-orange-100 dark:bg-orange-900 rounded">
                                <p class="text-sm">Bist du Vereinsmitglied? <a href="{{ route('login') }}" class="underline font-bold">Jetzt einloggen</a> um kostenlos teilzunehmen!</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('fantreffen.2026.store') }}" id="fantreffen-form" class="space-y-4">
                            @csrf

                            @guest
                                <div>
                                    <label class="block text-sm font-medium mb-2">Vorname *</label>
                                    <input type="text" name="vorname" value="{{ old('vorname') }}" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Nachname *</label>
                                    <input type="text" name="nachname" value="{{ old('nachname') }}" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">E-Mail *</label>
                                    <input type="email" name="email" value="{{ old('email') }}" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                            @else
                                <div class="p-4 bg-green-100 dark:bg-green-900 rounded">
                                    <p class="text-sm">✅ Angemeldet als <strong>{{ $user->vorname }} {{ $user->nachname }}</strong></p>
                                    <p class="text-sm mt-1">Deine Teilnahme ist <strong>kostenlos</strong>!</p>
                                </div>
                            @endguest

                            <div>
                                <label class="block text-sm font-medium mb-2">Mobile Rufnummer (optional)</label>
                                <input type="tel" name="mobile" value="{{ old('mobile', optional($user)->mobile ?? '') }}" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" placeholder="+49 123 456789">
                                <p class="text-xs text-gray-500 mt-1">Für WhatsApp-Updates</p>
                            </div>

                            @if(!$tshirtDeadlinePassed)
                                <div class="border-t pt-4">
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="tshirt_bestellt" id="tshirt_bestellt" value="1" 
                                               {{ old('tshirt_bestellt') ? 'checked' : '' }}
                                               class="w-5 h-5 mt-0.5">
                                        <div>
                                            <span class="font-medium">Event-T-Shirt bestellen</span>
                                            <p class="text-xs text-gray-500 mt-1">25,00 € Spende{{ !Auth::check() ? ' (zusammen mit Teilnahme: 30,00 €)' : '' }}</p>
                                        </div>
                                    </label>
                                    
                                    <div id="tshirt-groesse-container" class="mt-3 hidden">
                                        <label class="block text-sm font-medium mb-2">T-Shirt-Größe *</label>
                                        <select name="tshirt_groesse" id="tshirt_groesse" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600">
                                            <option value="">Bitte wählen...</option>
                                            <option value="XS" {{ old('tshirt_groesse') === 'XS' ? 'selected' : '' }}>XS</option>
                                            <option value="S" {{ old('tshirt_groesse') === 'S' ? 'selected' : '' }}>S</option>
                                            <option value="M" {{ old('tshirt_groesse') === 'M' ? 'selected' : '' }}>M</option>
                                            <option value="L" {{ old('tshirt_groesse') === 'L' ? 'selected' : '' }}>L</option>
                                            <option value="XL" {{ old('tshirt_groesse') === 'XL' ? 'selected' : '' }}>XL</option>
                                            <option value="XXL" {{ old('tshirt_groesse') === 'XXL' ? 'selected' : '' }}>XXL</option>
                                            <option value="XXXL" {{ old('tshirt_groesse') === 'XXXL' ? 'selected' : '' }}>XXXL</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <button type="submit" class="w-full px-6 py-3 bg-[#8B0116] text-white font-bold rounded-lg hover:bg-[#6b000e] transition">
                                @if($paymentAmount > 0)
                                    Weiter zur Zahlung ({{ number_format($paymentAmount, 2, ',', '.') }} €)
                                @else
                                    Jetzt anmelden
                                @endif
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/fantreffen.js'])
</x-slot>
</x-app-layout>

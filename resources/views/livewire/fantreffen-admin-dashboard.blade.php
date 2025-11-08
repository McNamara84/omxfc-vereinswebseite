<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Maddrax-Fantreffen 2026 – Anmeldungen
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Verwaltung aller Anmeldungen zum Fantreffen am 9. Mai 2026
            </p>
        </div>

        {{-- Success Message --}}
        @if (session()->has('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-400 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Statistik-Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Gesamt</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['total'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    {{ $stats['mitglieder'] }} Mitglieder, {{ $stats['gaeste'] }} Gäste
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">T-Shirts bestellt</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['tshirts'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    {{ $stats['tshirts_offen'] }} noch offen
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Zahlungen ausstehend</div>
                <div class="text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mt-2">{{ $stats['zahlungen_ausstehend'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    {{ number_format($stats['zahlungen_offen_betrag'], 2, ',', '.') }} € offen
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <button 
                    wire:click="exportCsv" 
                    class="w-full h-full flex flex-col items-center justify-center text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-[#ff4b63] transition-colors"
                >
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="font-medium">CSV Export</span>
                </button>
            </div>
        </div>

        {{-- Filter & Suche --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Filter & Suche</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                {{-- Mitgliedsstatus --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mitgliedsstatus</label>
                    <select wire:model.live="filterMemberStatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="alle">Alle</option>
                        <option value="mitglieder">Nur Mitglieder</option>
                        <option value="gaeste">Nur Gäste</option>
                    </select>
                </div>

                {{-- T-Shirt --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">T-Shirt</label>
                    <select wire:model.live="filterTshirt" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="alle">Alle</option>
                        <option value="mit_tshirt">Mit T-Shirt</option>
                        <option value="ohne_tshirt">Ohne T-Shirt</option>
                    </select>
                </div>

                {{-- Zahlungsstatus --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Zahlungsstatus</label>
                    <select wire:model.live="filterPayment" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="alle">Alle</option>
                        <option value="bezahlt">Bezahlt</option>
                        <option value="ausstehend">Ausstehend</option>
                        <option value="kostenlos">Kostenlos</option>
                    </select>
                </div>

                {{-- Zahlungseingang --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Zahlungseingang</label>
                    <select wire:model.live="filterZahlungseingang" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="alle">Alle</option>
                        <option value="erhalten">Erhalten</option>
                        <option value="ausstehend">Ausstehend</option>
                    </select>
                </div>

                {{-- T-Shirt fertig --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">T-Shirt Status</label>
                    <select wire:model.live="filterTshirtFertig" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                        <option value="alle">Alle</option>
                        <option value="fertig">Fertig</option>
                        <option value="offen">Offen</option>
                    </select>
                </div>

                {{-- Suche --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Suche</label>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Name oder E-Mail..." 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white"
                    >
                </div>
            </div>
        </div>

        {{-- Anmeldungen Tabelle --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-Mail</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mobil</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">T-Shirt</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Zahlung</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($anmeldungen as $anmeldung)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $anmeldung->full_name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $anmeldung->created_at->format('d.m.Y H:i') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $anmeldung->registrant_email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $anmeldung->mobile ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($anmeldung->ist_mitglied)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Mitglied
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                            Gast
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($anmeldung->tshirt_bestellt)
                                        <div class="text-sm text-gray-900 dark:text-white font-medium">
                                            {{ $anmeldung->tshirt_groesse }}
                                        </div>
                                        <button 
                                            wire:click="toggleTshirtFertig({{ $anmeldung->id }})"
                                            class="mt-1 text-xs px-2 py-1 rounded {{ $anmeldung->tshirt_fertig ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' }}"
                                        >
                                            {{ $anmeldung->tshirt_fertig ? '✓ Fertig' : 'Offen' }}
                                        </button>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format($anmeldung->payment_amount, 2, ',', '.') }} €
                                    </div>
                                    <div class="mt-1">
                                        @if ($anmeldung->payment_status === 'free')
                                            <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                Kostenlos
                                            </span>
                                        @else
                                            <button 
                                                wire:click="toggleZahlungseingang({{ $anmeldung->id }})"
                                                class="text-xs px-2 py-1 rounded {{ $anmeldung->zahlungseingang ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}"
                                            >
                                                {{ $anmeldung->zahlungseingang ? '✓ Erhalten' : 'Ausstehend' }}
                                            </button>
                                        @endif
                                    </div>
                                    @if ($anmeldung->paypal_transaction_id)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            PayPal: {{ substr($anmeldung->paypal_transaction_id, 0, 12) }}...
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    @if ($anmeldung->user)
                                        <a 
                                            href="{{ route('profile.view', $anmeldung->user) }}" 
                                            class="text-[#8B0116] dark:text-[#ff4b63] hover:underline"
                                            target="_blank"
                                        >
                                            Profil
                                        </a>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    Keine Anmeldungen gefunden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $anmeldungen->links() }}
            </div>
        </div>
    </div>
</div>

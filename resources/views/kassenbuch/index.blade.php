<x-app-layout>
    <x-member-page>
            @if(session('status'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-800 dark:bg-green-800 dark:border-green-700 dark:text-green-100 rounded">
                {{ session('status') }}
            </div>
            @endif
            
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 dark:bg-red-800 dark:border-red-700 dark:text-red-100 rounded">
                {{ session('error') }}
            </div>
            @endif
            
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-red-400 mb-6">Kassenbuch</h1>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Card 1: Mitgliedsbeitrag Status (Für alle Rollen) -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Dein Mitgliedsbeitrag</h2>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Dein aktueller Mitgliedsbeitrag:</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $memberData->mitgliedsbeitrag ? number_format($memberData->mitgliedsbeitrag, 2, ',', '.') . ' €' : 'Nicht festgelegt' }}
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Bezahlt bis:</p>
                        @if($memberData->bezahlt_bis)
                            @php
                                $bezahlt_bis = \Carbon\Carbon::parse($memberData->bezahlt_bis);
                                $heute = \Carbon\Carbon::now();
                                $differenz = $heute->diffInDays($bezahlt_bis, false);
                            @endphp
                            
                            @if($differenz < 0)
                                <p class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">
                                    Abgelaufen: {{ $bezahlt_bis->format('d.m.Y') }}
                                </p>
                                <div class="mt-3 p-3 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-md text-sm">
                                    <strong>Achtung:</strong> Deine Mitgliedschaft ist abgelaufen! Bitte kontaktiere umgehend den Kassenwart, um deine Mitgliedschaft zu verlängern.
                                </div>
                            @elseif($renewalWarning)
                                <p class="mt-1 text-lg font-semibold text-yellow-600 dark:text-yellow-400">
                                    {{ $bezahlt_bis->format('d.m.Y') }}
                                </p>
                                <div class="mt-3 p-3 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-md text-sm">
                                    <strong>Hinweis:</strong> Bitte denke daran rechtzeitig deine Mitgliedschaft zu verlängern, da deine Mitgliedschaft sonst erlischt.
                                </div>
                            @else
                                <p class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">
                                    {{ $bezahlt_bis->format('d.m.Y') }}
                                </p>
                            @endif
                        @else
                            <p class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">
                                Nicht festgelegt
                            </p>
                        @endif
                    </div>
                </div>
                
                <!-- Card 2: Aktueller Kassenstand (Für alle Rollen) -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Aktueller Kassenstand</h2>
                    
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Kassenstand zum {{ \Carbon\Carbon::parse($kassenstand->letzte_aktualisierung)->format('d.m.Y') }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $kassenstand->betrag >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format($kassenstand->betrag, 2, ',', '.') }} €
                        </p>
                    </div>
                </div>
                
                @if($canViewKassenbuch)
                <!-- Card 3: Mitgliederliste mit Zahlungsstatus (Für Vorstand und Kassenwart) -->
                <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Zahlungsstatus der Mitglieder</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mitglied</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-Mail</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Beitrag</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bezahlt bis</th>
                                    @if($userRole === \App\Enums\Role::Kassenwart || $userRole === \App\Enums\Role::Admin)
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktionen</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($members as $member)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('profile.view', $member->id) }}" class="flex items-center">
                                            <div class="h-8 w-8 flex-shrink-0">
                                                <img loading="lazy" class="h-8 w-8 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $member->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $member->vorname }} {{ $member->nachname }}</div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $member->email }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $member->mitgliedsbeitrag ? number_format($member->mitgliedsbeitrag, 2, ',', '.') . ' €' : '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($member->bezahlt_bis)
                                            @php
                                                $bezahlt_bis = \Carbon\Carbon::parse($member->bezahlt_bis);
                                                $heute = \Carbon\Carbon::now();
                                                $differenz = $heute->diffInDays($bezahlt_bis, false);
                                            @endphp
                                            
                                            @if($differenz < 0)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                    Überfällig: {{ $bezahlt_bis->format('d.m.Y') }}
                                                </span>
                                            @elseif($differenz <= 30)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                    {{ $bezahlt_bis->format('d.m.Y') }}
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                    {{ $bezahlt_bis->format('d.m.Y') }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                Nicht festgelegt
                                            </span>
                                        @endif
                                    </td>
                                    @if($userRole === \App\Enums\Role::Kassenwart || $userRole === \App\Enums\Role::Admin)
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <button type="button"
                                                x-data
                                                @click="$dispatch('edit-payment-modal', { 
                                                    user_id: '{{ $member->id }}',
                                                    user_name: '{{ addslashes($member->name) }}',
                                                    mitgliedsbeitrag: '{{ $member->mitgliedsbeitrag }}',
                                                    bezahlt_bis: '{{ $member->bezahlt_bis ? $member->bezahlt_bis->format('Y-m-d') : '' }}',
                                                    mitglied_seit: '{{ $member->mitglied_seit ? $member->mitglied_seit->format('Y-m-d') : '' }}'
                                                })"
                                                data-kassenbuch-edit="true"
                                                data-user-name="{{ $member->name }}"
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-[#8B0116] hover:bg-red-700 focus:outline-none focus:border-red-700 focus:shadow-outline-red active:bg-red-800 transition ease-in-out duration-150">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                            </svg>
                                            Bearbeiten
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Card 4: Kassenbuch (Für Vorstand und Kassenwart) -->
                <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Kassenbuch</h2>

                        @if($canManageKassenbuch)
                        <button type="button" 
                                x-data 
                                @click="$dispatch('kassenbuch-modal')" 
                                data-kassenbuch-modal-trigger="true"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-[#8B0116] hover:bg-red-700 focus:outline-none focus:border-red-700 focus:shadow-outline-red active:bg-red-800 transition ease-in-out duration-150">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Eintrag hinzufügen
                        </button>
                        @endif
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Datum</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Beschreibung</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Einnahme</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ausgabe</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Erstellt von</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @if($kassenbuchEntries->count() > 0)
                                    @foreach($kassenbuchEntries as $entry)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($entry->buchungsdatum)->format('d.m.Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $entry->beschreibung }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($entry->betrag > 0)
                                                <span class="text-green-600 dark:text-green-400 font-medium">{{ number_format($entry->betrag, 2, ',', '.') }} €</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($entry->betrag < 0)
                                                <span class="text-red-600 dark:text-red-400 font-medium">{{ number_format(abs($entry->betrag), 2, ',', '.') }} €</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <a href="{{ route('profile.view', $entry->creator->id) }}" class="text-[#8B0116] hover:underline">{{ $entry->creator->name }}</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Keine Einträge vorhanden.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>

            @if($canManageKassenbuch)
            <!-- Modal für die Bearbeitung von Zahlungsdaten -->
            <div x-data="{ open: false, user_id: '', user_name: '', mitgliedsbeitrag: '', bezahlt_bis: '', mitglied_seit: '' }"
                 x-show="open" 
                 x-on:edit-payment-modal.window="
                    open = true; 
                    user_id = $event.detail.user_id; 
                    user_name = $event.detail.user_name; 
                    mitgliedsbeitrag = $event.detail.mitgliedsbeitrag;
                    bezahlt_bis = $event.detail.bezahlt_bis;
                    mitglied_seit = $event.detail.mitglied_seit;
                 "
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto" 
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                    <div x-show="open" 
                         x-transition:enter="ease-out duration-300" 
                         x-transition:enter-start="opacity-0" 
                         x-transition:enter-end="opacity-100" 
                         x-transition:leave="ease-in duration-200" 
                         x-transition:leave-start="opacity-100" 
                         x-transition:leave-end="opacity-0" 
                         class="absolute inset-0 z-0 transition-opacity" 
                         aria-hidden="true">
                        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
                    </div>
                    
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative z-10 bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6"
                         role="dialog"
                         aria-modal="true"
                         aria-labelledby="edit-payment-title"
                         aria-describedby="edit-payment-description">
                        <div class="flex justify-between items-center mb-4">
                            <h3 id="edit-payment-title" class="text-lg font-medium text-gray-900 dark:text-white">Zahlungsdaten bearbeiten</h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <p id="edit-payment-description" class="text-sm text-gray-500 dark:text-gray-400 mb-4" x-text="'Mitglied: ' + user_name"></p>
                        
                        <form :action="'/kassenbuch/zahlung-aktualisieren/' + user_id" method="POST">
                            @csrf
                            @method('PUT')
                              <x-form name="mitgliedsbeitrag" label="Mitgliedsbeitrag (€)" class="mb-4">
                                  <input id="mitgliedsbeitrag" name="mitgliedsbeitrag" aria-describedby="mitgliedsbeitrag-error" type="number" step="0.01" min="0" x-model="mitgliedsbeitrag" class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                              </x-form>
                              <x-form name="bezahlt_bis" label="Bezahlt bis" class="mb-4">
                                  <input id="bezahlt_bis" name="bezahlt_bis" aria-describedby="bezahlt_bis-error" type="date" x-model="bezahlt_bis" class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                              </x-form>
                              <x-form name="mitglied_seit" label="Mitglied seit" class="mb-4">
                                  <input id="mitglied_seit" name="mitglied_seit" aria-describedby="mitglied_seit-error" type="date" x-model="mitglied_seit" class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                              </x-form>
                            <div class="mt-6 flex justify-end">
                                <button type="button" @click="open = false" class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none">
                                    Abbrechen
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#8B0116] border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Modal für die Erstellung von Kassenbucheinträgen -->
            <div x-data="{ open: false }" 
                 x-show="open" 
                 x-on:kassenbuch-modal.window="open = true" 
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto" 
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                    <div x-show="open" 
                         x-transition:enter="ease-out duration-300" 
                         x-transition:enter-start="opacity-0" 
                         x-transition:enter-end="opacity-100" 
                         x-transition:leave="ease-in duration-200" 
                         x-transition:leave-start="opacity-100" 
                         x-transition:leave-end="opacity-0" 
                        class="absolute inset-0 z-0 transition-opacity" 
                         aria-hidden="true">
                        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
                    </div>
                    
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="relative z-10 bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6"
                         role="dialog"
                         aria-modal="true"
                         aria-labelledby="kassenbuch-modal-title"
                         aria-describedby="kassenbuch-modal-description">
                        <div class="flex justify-between items-center mb-4">
                            <h3 id="kassenbuch-modal-title" class="text-lg font-medium text-gray-900 dark:text-white">Kassenbucheintrag hinzufügen</h3>
                            <button @click="open = false" class="text-gray-400 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <p id="kassenbuch-modal-description" class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Erfasse hier Einnahmen und Ausgaben des Vereins und halte die Finanzdaten aktuell.
                        </p>

                        <form action="{{ route('kassenbuch.add-entry') }}" method="POST">
                            @csrf
                            
                                <x-form name="buchungsdatum" label="Buchungsdatum" class="mb-4">
                                    <input id="buchungsdatum" name="buchungsdatum" aria-describedby="buchungsdatum-error" type="date" required value="{{ date('Y-m-d') }}" class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                                </x-form>

                                <x-form name="beschreibung" label="Beschreibung" class="mb-4">
                                    <input id="beschreibung" name="beschreibung" aria-describedby="beschreibung-error" type="text" required class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                                </x-form>

                                <x-form name="betrag" label="Betrag (€)" class="mb-4">
                                    <input id="betrag" name="betrag" aria-describedby="betrag-error" type="number" step="0.01" min="0.01" required class="shadow-sm focus:ring-[#8B0116] focus:border-[#8B0116] block w-full sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-md" />
                                </x-form>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Typ</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="typ" value="einnahme" checked class="form-radio h-4 w-4 text-[#8B0116] focus:ring-[#8B0116] border-gray-300 dark:border-gray-700">
                                        <span class="ml-2 text-gray-700 dark:text-gray-300">Einnahme</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="typ" value="ausgabe" class="form-radio h-4 w-4 text-[#8B0116] focus:ring-[#8B0116] border-gray-300 dark:border-gray-700">
                                        <span class="ml-2 text-gray-700 dark:text-gray-300">Ausgabe</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <button type="button" @click="open = false" class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none">
                                    Abbrechen
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#8B0116] border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Hinzufügen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            @endif
    </x-member-page>
</x-app-layout>
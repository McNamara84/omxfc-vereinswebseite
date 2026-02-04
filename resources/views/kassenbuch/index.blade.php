<x-app-layout>
    <x-member-page>
        {{-- Flash Messages --}}
        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif
        
        @if(session('error'))
            <x-alert icon="o-exclamation-triangle" class="alert-error mb-4" dismissible>
                {{ session('error') }}
            </x-alert>
        @endif
        
        {{-- Header --}}
        <x-header title="Kassenbuch" separator />
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Card 1: Mitgliedsbeitrag Status (Für alle Rollen) --}}
            <x-card title="Dein Mitgliedsbeitrag" shadow>
                <div class="mb-4">
                    <p class="text-sm text-base-content/60">Dein aktueller Mitgliedsbeitrag:</p>
                    <p class="text-xl font-semibold">
                        {{ $memberData->mitgliedsbeitrag ? number_format($memberData->mitgliedsbeitrag, 2, ',', '.') . ' €' : 'Nicht festgelegt' }}
                    </p>
                </div>
                
                <div>
                    <p class="text-sm text-base-content/60">Bezahlt bis:</p>
                    @if($memberData->bezahlt_bis)
                        @php
                            $bezahlt_bis = \Carbon\Carbon::parse($memberData->bezahlt_bis);
                            $heute = \Carbon\Carbon::now();
                            $differenz = $heute->diffInDays($bezahlt_bis, false);
                        @endphp
                        
                        @if($differenz < 0)
                            <p class="mt-1 text-lg font-semibold text-error">
                                Abgelaufen: {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                            <x-alert icon="o-exclamation-triangle" class="alert-error mt-3">
                                <strong>Achtung:</strong> Deine Mitgliedschaft ist abgelaufen! Bitte kontaktiere umgehend den Kassenwart, um deine Mitgliedschaft zu verlängern.
                            </x-alert>
                        @elseif($renewalWarning)
                            <p class="mt-1 text-lg font-semibold text-warning">
                                {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                            <x-alert icon="o-exclamation-triangle" class="alert-warning mt-3">
                                <strong>Hinweis:</strong> Bitte denke daran rechtzeitig deine Mitgliedschaft zu verlängern, da deine Mitgliedschaft sonst erlischt.
                            </x-alert>
                        @else
                            <p class="mt-1 text-lg font-semibold text-success">
                                {{ $bezahlt_bis->format('d.m.Y') }}
                            </p>
                        @endif
                    @else
                        <p class="mt-1 text-lg font-semibold text-error">
                            Nicht festgelegt
                        </p>
                    @endif
                </div>
            </x-card>
            
            {{-- Card 2: Aktueller Kassenstand (Für alle Rollen) --}}
            <x-card title="Aktueller Kassenstand" shadow>
                <p class="text-sm text-base-content/60">Kassenstand zum {{ \Carbon\Carbon::parse($kassenstand->letzte_aktualisierung)->format('d.m.Y') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $kassenstand->betrag >= 0 ? 'text-success' : 'text-error' }}">
                    {{ number_format($kassenstand->betrag, 2, ',', '.') }} €
                </p>
            </x-card>
            
            @if($canViewKassenbuch)
                {{-- Card: Offene Bearbeitungsanfragen (für Vorstand und Admin sichtbar) --}}
                @if($canProcessEditRequests && $pendingEditRequests && $pendingEditRequests->count() > 0)
                    <x-card class="md:col-span-2" shadow>
                        <x-slot:title>
                            <div class="flex items-center">
                                <x-icon name="o-bell" class="w-5 h-5 mr-2 text-warning" />
                                Offene Bearbeitungsanfragen ({{ $pendingEditRequests->count() }})
                            </div>
                        </x-slot:title>
                        
                        <div class="space-y-4">
                            @foreach($pendingEditRequests as $request)
                                <div class="border border-warning/30 rounded-lg p-4 bg-warning/10">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                                        <div class="flex-1">
                                            <p class="font-medium">{{ $request->entry->beschreibung }}</p>
                                            <p class="text-sm text-base-content/60 mt-1">
                                                {{ number_format(abs($request->entry->betrag), 2, ',', '.') }} € 
                                                ({{ $request->entry->typ->value === 'einnahme' ? 'Einnahme' : 'Ausgabe' }})
                                                – {{ $request->entry->buchungsdatum->format('d.m.Y') }}
                                            </p>
                                            <p class="text-sm text-base-content/60 mt-2">
                                                <strong>Begründung:</strong> 
                                                {{ $request->getFormattedReason() }}
                                            </p>
                                            <p class="text-xs text-base-content/40 mt-1">
                                                Angefragt von 
                                                <a href="{{ route('profile.view', $request->requester->id) }}" class="text-primary hover:underline">{{ $request->requester->name }}</a>
                                                am {{ $request->created_at->format('d.m.Y \u\m H:i') }} Uhr
                                            </p>
                                        </div>
                                        
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            {{-- Freigeben --}}
                                            <form action="{{ route('kassenbuch.approve-edit', $request) }}" method="POST">
                                                @csrf
                                                <x-button type="submit" label="Freigeben" icon="o-check" class="btn-success btn-sm" />
                                            </form>
                                            
                                            {{-- Ablehnen --}}
                                            <x-button 
                                                label="Ablehnen" 
                                                icon="o-x-mark"
                                                class="btn-error btn-sm"
                                                x-data
                                                @click="$dispatch('reject-edit-modal', { id: {{ $request->id }}, beschreibung: {{ Js::from($request->entry->beschreibung) }} })" />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                {{-- Card 3: Mitgliederliste mit Zahlungsstatus (Für Vorstand und Kassenwart) --}}
                <x-card title="Zahlungsstatus der Mitglieder" class="md:col-span-2" shadow>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mitglied</th>
                                    <th>E-Mail</th>
                                    <th>Beitrag</th>
                                    <th>Bezahlt bis</th>
                                    @if($userRole === \App\Enums\Role::Kassenwart || $userRole === \App\Enums\Role::Admin)
                                        <th>Aktionen</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $member)
                                    <tr class="hover">
                                        <td>
                                            <a href="{{ route('profile.view', $member->id) }}" class="flex items-center gap-3">
                                                <x-avatar :image="$member->profile_photo_url" class="!w-8 !h-8" />
                                                <div>
                                                    <div class="font-medium">{{ $member->name }}</div>
                                                    <div class="text-xs text-base-content/60">{{ $member->vorname }} {{ $member->nachname }}</div>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-base-content/60">
                                            {{ $member->email }}
                                        </td>
                                        <td class="text-base-content/60">
                                            {{ $member->mitgliedsbeitrag ? number_format($member->mitgliedsbeitrag, 2, ',', '.') . ' €' : '-' }}
                                        </td>
                                        <td>
                                            @if($member->bezahlt_bis)
                                                @php
                                                    $bezahlt_bis = \Carbon\Carbon::parse($member->bezahlt_bis);
                                                    $heute = \Carbon\Carbon::now();
                                                    $differenz = $heute->diffInDays($bezahlt_bis, false);
                                                @endphp
                                                
                                                @if($differenz < 0)
                                                    <x-badge value="Überfällig: {{ $bezahlt_bis->format('d.m.Y') }}" class="badge-error" />
                                                @elseif($differenz <= 30)
                                                    <x-badge value="{{ $bezahlt_bis->format('d.m.Y') }}" class="badge-warning" />
                                                @else
                                                    <x-badge value="{{ $bezahlt_bis->format('d.m.Y') }}" class="badge-success" />
                                                @endif
                                            @else
                                                <x-badge value="Nicht festgelegt" class="badge-ghost" />
                                            @endif
                                        </td>
                                        @if($userRole === \App\Enums\Role::Kassenwart || $userRole === \App\Enums\Role::Admin)
                                            <td>
                                                <x-button 
                                                    label="Bearbeiten" 
                                                    icon="o-pencil" 
                                                    class="btn-primary btn-sm"
                                                    x-data
                                                    data-kassenbuch-edit="true"
                                                    data-user-name="{{ $member->name }}"
                                                    @click="$dispatch('edit-payment-modal', { 
                                                        user_id: '{{ $member->id }}',
                                                        user_name: {{ Js::from($member->name) }},
                                                        mitgliedsbeitrag: '{{ $member->mitgliedsbeitrag }}',
                                                        bezahlt_bis: '{{ $member->bezahlt_bis ? $member->bezahlt_bis->format('Y-m-d') : '' }}',
                                                        mitglied_seit: '{{ $member->mitglied_seit ? $member->mitglied_seit->format('Y-m-d') : '' }}'
                                                    })" />
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
                
                {{-- Card 4: Kassenbuch (Für Vorstand und Kassenwart) --}}
                <x-card class="md:col-span-2" shadow>
                    <x-slot:title>
                        <div class="flex justify-between items-center w-full">
                            <span>Kassenbuch</span>
                            @if($canManageKassenbuch)
                                <x-button 
                                    label="Eintrag hinzufügen" 
                                    icon="o-plus" 
                                    class="btn-primary btn-sm"
                                    x-data 
                                    @click="$dispatch('kassenbuch-modal')" 
                                    data-kassenbuch-modal-trigger="true" />
                            @endif
                        </div>
                    </x-slot:title>
                    
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Beschreibung</th>
                                    <th>Einnahme</th>
                                    <th>Ausgabe</th>
                                    <th>Erstellt von</th>
                                    @if($canManageKassenbuch)
                                        <th>Aktionen</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kassenbuchEntries as $entry)
                                    <tr class="hover">
                                        <td class="text-base-content/60">
                                            {{ \Carbon\Carbon::parse($entry->buchungsdatum)->format('d.m.Y') }}
                                        </td>
                                        <td>
                                            <span>{{ $entry->beschreibung }}</span>
                                            @if($entry->wasEdited())
                                                <span class="ml-1 text-xs text-base-content/40" title="Zuletzt bearbeitet am {{ $entry->last_edited_at->format('d.m.Y H:i') }} Uhr">(bearbeitet)</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($entry->betrag > 0)
                                                <span class="text-success font-medium">{{ number_format($entry->betrag, 2, ',', '.') }} €</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($entry->betrag < 0)
                                                <span class="text-error font-medium">{{ number_format(abs($entry->betrag), 2, ',', '.') }} €</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('profile.view', $entry->creator->id) }}" class="text-primary hover:underline">{{ $entry->creator->name }}</a>
                                        </td>
                                        @if($canManageKassenbuch)
                                            <td>
                                                @if($entry->hasApprovedEditRequest())
                                                    {{-- Bearbeiten-Button (aktiv nach Freigabe) --}}
                                                    <x-button 
                                                        label="Bearbeiten" 
                                                        icon="o-pencil" 
                                                        class="btn-success btn-xs"
                                                        x-data
                                                        @click="$dispatch('edit-entry-modal', {
                                                            id: {{ $entry->id }},
                                                            buchungsdatum: '{{ $entry->buchungsdatum->format('Y-m-d') }}',
                                                            beschreibung: {{ Js::from($entry->beschreibung) }},
                                                            betrag: '{{ abs($entry->betrag) }}',
                                                            typ: '{{ $entry->typ->value }}'
                                                        })" />
                                                @elseif($entry->hasPendingEditRequest())
                                                    {{-- Anfrage läuft --}}
                                                    <x-badge value="Anfrage läuft" class="badge-warning animate-pulse" icon="o-clock" />
                                                @else
                                                    {{-- Bearbeitung anfragen --}}
                                                    <x-button 
                                                        label="Bearbeiten anfragen" 
                                                        icon="o-lock-closed" 
                                                        class="btn-ghost btn-xs"
                                                        x-data
                                                        @click="$dispatch('request-edit-modal', {
                                                            id: {{ $entry->id }},
                                                            beschreibung: {{ Js::from($entry->beschreibung) }}
                                                        })" />
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canManageKassenbuch ? 6 : 5 }}" class="text-center py-8 text-base-content/60">
                                            <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                                            Keine Einträge vorhanden.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- MODALE --}}
        @if($canManageKassenbuch)
            {{-- Modal: Zahlungsdaten bearbeiten --}}
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
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div x-show="open" 
                         x-transition:enter="ease-out duration-300" 
                         x-transition:enter-start="opacity-0" 
                         x-transition:enter-end="opacity-100" 
                         x-transition:leave="ease-in duration-200" 
                         x-transition:leave-start="opacity-100" 
                         x-transition:leave-end="opacity-0" 
                         class="fixed inset-0 bg-base-300/75" 
                         @click="open = false"></div>
                    
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-base-100 rounded-box shadow-xl max-w-lg w-full p-6"
                         role="dialog"
                         aria-modal="true">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Zahlungsdaten bearbeiten</h3>
                            <x-button icon="o-x-mark" class="btn-ghost btn-sm" @click="open = false" />
                        </div>

                        <p class="text-sm text-base-content/60 mb-4" x-text="'Mitglied: ' + user_name"></p>
                        
                        <form :action="'/kassenbuch/zahlung-aktualisieren/' + user_id" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="label" for="mitgliedsbeitrag">
                                        <span class="label-text">Mitgliedsbeitrag (€)</span>
                                    </label>
                                    <input id="mitgliedsbeitrag" name="mitgliedsbeitrag" aria-describedby="mitgliedsbeitrag-error" type="number" step="0.01" min="0" x-model="mitgliedsbeitrag" class="input input-bordered w-full" />
                                    @error('mitgliedsbeitrag') <span id="mitgliedsbeitrag-error" class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="label" for="bezahlt_bis">
                                        <span class="label-text">Bezahlt bis</span>
                                    </label>
                                    <input id="bezahlt_bis" name="bezahlt_bis" type="date" x-model="bezahlt_bis" class="input input-bordered w-full" />
                                    @error('bezahlt_bis') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="label" for="mitglied_seit">
                                        <span class="label-text">Mitglied seit</span>
                                    </label>
                                    <input id="mitglied_seit" name="mitglied_seit" type="date" x-model="mitglied_seit" class="input input-bordered w-full" />
                                    @error('mitglied_seit') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end gap-2">
                                <x-button label="Abbrechen" class="btn-ghost" @click="open = false" />
                                <x-button label="Speichern" type="submit" class="btn-primary" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            {{-- Modal: Kassenbucheintrag hinzufügen --}}
            <div x-data="{ open: false }" 
                 x-show="open" 
                 x-on:kassenbuch-modal.window="open = true" 
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto" 
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div x-show="open" 
                         x-transition:enter="ease-out duration-300" 
                         x-transition:enter-start="opacity-0" 
                         x-transition:enter-end="opacity-100" 
                         x-transition:leave="ease-in duration-200" 
                         x-transition:leave-start="opacity-100" 
                         x-transition:leave-end="opacity-0" 
                         class="fixed inset-0 bg-base-300/75" 
                         @click="open = false"></div>
                    
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-base-100 rounded-box shadow-xl max-w-lg w-full p-6"
                         role="dialog"
                         aria-modal="true">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Kassenbucheintrag hinzufügen</h3>
                            <x-button icon="o-x-mark" class="btn-ghost btn-sm" @click="open = false" />
                        </div>

                        <p class="text-sm text-base-content/60 mb-4">
                            Erfasse hier Einnahmen und Ausgaben des Vereins und halte die Finanzdaten aktuell.
                        </p>

                        <form action="{{ route('kassenbuch.add-entry') }}" method="POST">
                            @csrf
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="label" for="buchungsdatum">
                                        <span class="label-text">Buchungsdatum</span>
                                    </label>
                                    <input id="buchungsdatum" name="buchungsdatum" aria-describedby="buchungsdatum-error" type="date" required value="{{ date('Y-m-d') }}" class="input input-bordered w-full" />
                                    @error('buchungsdatum') <span id="buchungsdatum-error" class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="label" for="beschreibung">
                                        <span class="label-text">Beschreibung</span>
                                    </label>
                                    <input id="beschreibung" name="beschreibung" type="text" required class="input input-bordered w-full" />
                                    @error('beschreibung') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="label" for="betrag">
                                        <span class="label-text">Betrag (€)</span>
                                    </label>
                                    <input id="betrag" name="betrag" type="number" step="0.01" min="0.01" required class="input input-bordered w-full" />
                                    @error('betrag') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                            
                                <div>
                                    <label class="label">
                                        <span class="label-text">Typ</span>
                                    </label>
                                    <div class="flex gap-4">
                                        <label class="label cursor-pointer gap-2">
                                            <input type="radio" name="typ" value="einnahme" checked class="radio radio-primary" />
                                            <span class="label-text">Einnahme</span>
                                        </label>
                                        <label class="label cursor-pointer gap-2">
                                            <input type="radio" name="typ" value="ausgabe" class="radio radio-primary" />
                                            <span class="label-text">Ausgabe</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end gap-2">
                                <x-button label="Abbrechen" class="btn-ghost" @click="open = false" />
                                <x-button label="Hinzufügen" type="submit" class="btn-primary" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Modal: Bearbeitung anfragen --}}
            <div x-data="{ open: false, entry_id: '', entry_desc: '' }"
                 x-show="open"
                 x-on:request-edit-modal.window="open = true; entry_id = $event.detail.id; entry_desc = $event.detail.beschreibung"
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 bg-base-300/75"
                         @click="open = false"></div>

                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-base-100 rounded-box shadow-xl max-w-lg w-full p-6"
                         role="dialog"
                         aria-modal="true">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Bearbeitung anfragen</h3>
                            <x-button icon="o-x-mark" class="btn-ghost btn-sm" @click="open = false" />
                        </div>

                        <p class="text-sm text-base-content/60 mb-2">Eintrag:</p>
                        <p class="text-sm font-medium mb-4" x-text="entry_desc"></p>

                        <form :action="'/kassenbuch/eintrag/' + entry_id + '/bearbeitung-anfragen'" method="POST">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <label class="label" for="reason_type">
                                        <span class="label-text">Begründung</span>
                                    </label>
                                    <select id="reason_type" name="reason_type" required class="select select-bordered w-full">
                                        <option value="">-- Bitte wählen --</option>
                                        @foreach($editReasonTypes as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="label" for="reason_text">
                                        <span class="label-text">Details (optional)</span>
                                    </label>
                                    <textarea id="reason_text" name="reason_text" rows="3" maxlength="500" placeholder="Optionale Details zur Begründung..." class="textarea textarea-bordered w-full"></textarea>
                                </div>
                            </div>

                            <p class="text-xs text-base-content/40 mt-2 mb-4">
                                Hinweis: Bei "Sonstiges" ist eine Begründung erforderlich.
                            </p>

                            <div class="mt-6 flex justify-end gap-2">
                                <x-button label="Abbrechen" class="btn-ghost" @click="open = false" />
                                <x-button label="Anfrage senden" type="submit" class="btn-primary" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Modal: Eintrag bearbeiten (nach Freigabe) --}}
            <div x-data="{ open: false, entry: { id: '', buchungsdatum: '', beschreibung: '', betrag: '', typ: 'einnahme' } }"
                 x-show="open"
                 x-on:edit-entry-modal.window="open = true; entry = $event.detail"
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 bg-base-300/75"
                         @click="open = false"></div>

                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-base-100 rounded-box shadow-xl max-w-lg w-full p-6"
                         role="dialog"
                         aria-modal="true">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Kassenbucheintrag bearbeiten</h3>
                            <x-button icon="o-x-mark" class="btn-ghost btn-sm" @click="open = false" />
                        </div>

                        <x-alert icon="o-check-circle" class="alert-success mb-4">
                            Die Bearbeitung dieses Eintrags wurde vom Vorstand freigegeben.
                        </x-alert>

                        <form :action="'/kassenbuch/eintrag/' + entry.id" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="space-y-4">
                                <div>
                                    <label class="label" for="edit_buchungsdatum">
                                        <span class="label-text">Buchungsdatum</span>
                                    </label>
                                    <input id="edit_buchungsdatum" name="buchungsdatum" type="date" required x-model="entry.buchungsdatum" class="input input-bordered w-full" />
                                </div>

                                <div>
                                    <label class="label" for="edit_beschreibung">
                                        <span class="label-text">Beschreibung</span>
                                    </label>
                                    <input id="edit_beschreibung" name="beschreibung" type="text" required x-model="entry.beschreibung" class="input input-bordered w-full" />
                                </div>

                                <div>
                                    <label class="label" for="edit_betrag">
                                        <span class="label-text">Betrag (€)</span>
                                    </label>
                                    <input id="edit_betrag" name="betrag" type="number" step="0.01" min="0.01" required x-model="entry.betrag" class="input input-bordered w-full" />
                                </div>

                                <div>
                                    <label class="label">
                                        <span class="label-text">Typ</span>
                                    </label>
                                    <div class="flex gap-4">
                                        <label class="label cursor-pointer gap-2">
                                            <input type="radio" name="typ" value="einnahme" x-model="entry.typ" class="radio radio-primary" />
                                            <span class="label-text">Einnahme</span>
                                        </label>
                                        <label class="label cursor-pointer gap-2">
                                            <input type="radio" name="typ" value="ausgabe" x-model="entry.typ" class="radio radio-primary" />
                                            <span class="label-text">Ausgabe</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-2">
                                <x-button label="Abbrechen" class="btn-ghost" @click="open = false" />
                                <x-button label="Änderungen speichern" type="submit" class="btn-primary" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: Anfrage ablehnen (für Vorstand und Admin) --}}
        @if($canProcessEditRequests)
            <div x-data="{ open: false, request_id: '', beschreibung: '' }"
                 x-show="open"
                 x-on:reject-edit-modal.window="open = true; request_id = $event.detail.id; beschreibung = $event.detail.beschreibung"
                 x-on:keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 overflow-y-auto"
                 style="display: none;">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 bg-base-300/75"
                         @click="open = false"></div>

                    <div x-show="open"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-base-100 rounded-box shadow-xl max-w-lg w-full p-6"
                         role="dialog"
                         aria-modal="true">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Bearbeitungsanfrage ablehnen</h3>
                            <x-button icon="o-x-mark" class="btn-ghost btn-sm" @click="open = false" />
                        </div>

                        <p class="text-sm text-base-content/60 mb-2">Eintrag:</p>
                        <p class="text-sm font-medium mb-4" x-text="beschreibung"></p>

                        <form :action="'/kassenbuch/anfrage/' + request_id + '/ablehnen'" method="POST">
                            @csrf

                            <div>
                                <label class="label" for="rejection_reason">
                                    <span class="label-text">Begründung (optional)</span>
                                </label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" placeholder="Optionale Begründung für die Ablehnung..." class="textarea textarea-bordered w-full"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-2">
                                <x-button label="Abbrechen" class="btn-ghost" @click="open = false" />
                                <x-button label="Ablehnen" type="submit" class="btn-error" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </x-member-page>
</x-app-layout>

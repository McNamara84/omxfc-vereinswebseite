@php
    // Filter-Optionen
    $memberStatusOptions = [
        ['id' => 'alle', 'name' => 'Alle'],
        ['id' => 'mitglieder', 'name' => 'Nur Mitglieder'],
        ['id' => 'gaeste', 'name' => 'Nur Gäste'],
    ];

    $tshirtOptions = [
        ['id' => 'alle', 'name' => 'Alle'],
        ['id' => 'mit_tshirt', 'name' => 'Mit T-Shirt'],
        ['id' => 'ohne_tshirt', 'name' => 'Ohne T-Shirt'],
    ];

    $paymentOptions = [
        ['id' => 'alle', 'name' => 'Alle'],
        ['id' => 'bezahlt', 'name' => 'Bezahlt'],
        ['id' => 'ausstehend', 'name' => 'Ausstehend'],
        ['id' => 'kostenlos', 'name' => 'Kostenlos'],
    ];

    $zahlungseingangOptions = [
        ['id' => 'alle', 'name' => 'Alle'],
        ['id' => 'erhalten', 'name' => 'Erhalten'],
        ['id' => 'ausstehend', 'name' => 'Ausstehend'],
    ];

    $tshirtFertigOptions = [
        ['id' => 'alle', 'name' => 'Alle'],
        ['id' => 'fertig', 'name' => 'Fertig'],
        ['id' => 'offen', 'name' => 'Offen'],
    ];

    // Tabellen-Header
    $headers = [
        ['key' => 'full_name', 'label' => 'Name'],
        ['key' => 'email', 'label' => 'E-Mail'],
        ['key' => 'mobile', 'label' => 'Mobil'],
        ['key' => 'status', 'label' => 'Status', 'class' => 'text-center'],
        ['key' => 'orga_team', 'label' => 'Orga-Team', 'class' => 'text-center'],
        ['key' => 'tshirt', 'label' => 'T-Shirt', 'class' => 'text-center'],
        ['key' => 'zahlung', 'label' => 'Zahlung', 'class' => 'text-center'],
        ['key' => 'profil', 'label' => 'Profil', 'class' => 'text-center'],
        ['key' => 'actions', 'label' => 'Löschen', 'class' => 'text-center'],
    ];
@endphp

<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <x-header title="Maddrax-Fantreffen 2026 – Anmeldungen" subtitle="Verwaltung aller Anmeldungen zum Fantreffen am 9. Mai 2026" separator>
            <x-slot:actions>
                <x-button 
                    label="VIP-Autoren verwalten" 
                    icon="o-star" 
                    link="{{ route('admin.fantreffen.vip-authors') }}"
                    class="btn-primary"
                />
            </x-slot:actions>
        </x-header>

        {{-- Flash Messages --}}
        @if (session()->has('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-6" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if (session()->has('error'))
            <x-alert icon="o-exclamation-circle" class="alert-error mb-6" dismissible>
                {{ session('error') }}
            </x-alert>
        @endif

        {{-- Statistik-Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <x-stat 
                title="Gesamt" 
                value="{{ $this->stats['total'] }}"
                description="{{ $this->stats['mitglieder'] }} Mitglieder, {{ $this->stats['gaeste'] }} Gäste"
                icon="o-users"
            />

            <x-stat 
                title="T-Shirts bestellt" 
                value="{{ $this->stats['tshirts'] }}"
                description="{{ $this->stats['tshirts_offen'] }} noch offen"
                icon="o-shopping-bag"
            />

            <x-stat 
                title="Zahlungen ausstehend" 
                value="{{ $this->stats['zahlungen_ausstehend'] }}"
                description="{{ number_format($this->stats['zahlungen_offen_betrag'], 2, ',', '.') }} € offen"
                icon="o-currency-euro"
                color="text-error"
            />

            <x-card class="flex items-center justify-center">
                <x-button 
                    wire:click="exportCsv" 
                    icon="o-document-arrow-down"
                    label="CSV Export"
                    class="btn-ghost btn-lg"
                />
            </x-card>
        </div>

        {{-- Filter & Suche --}}
        <x-card title="Filter & Suche" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-select 
                    label="Mitgliedsstatus" 
                    :options="$memberStatusOptions"
                    wire:model.live="filterMemberStatus"
                    icon="o-user-group"
                />

                <x-select 
                    label="T-Shirt" 
                    :options="$tshirtOptions"
                    wire:model.live="filterTshirt"
                    icon="o-shopping-bag"
                />

                <x-select 
                    label="Zahlungsstatus" 
                    :options="$paymentOptions"
                    wire:model.live="filterPayment"
                    icon="o-credit-card"
                />

                <x-select 
                    label="Zahlungseingang" 
                    :options="$zahlungseingangOptions"
                    wire:model.live="filterZahlungseingang"
                    icon="o-banknotes"
                />

                <x-select 
                    label="T-Shirt Status" 
                    :options="$tshirtFertigOptions"
                    wire:model.live="filterTshirtFertig"
                    icon="o-check-badge"
                />

                <x-input 
                    label="Suche"
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Name oder E-Mail..." 
                    icon="o-magnifying-glass"
                    clearable
                />
            </div>
        </x-card>

        {{-- Anmeldungen Tabelle --}}
        <x-card>
            <x-table :headers="$headers" :rows="$this->anmeldungen" striped>
                {{-- Name Spalte --}}
                @scope('cell_full_name', $anmeldung)
                    <div>
                        <div class="font-medium">{{ $anmeldung->full_name }}</div>
                        <div class="text-xs opacity-60">{{ $anmeldung->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                @endscope

                {{-- E-Mail Spalte --}}
                @scope('cell_email', $anmeldung)
                    {{ $anmeldung->registrant_email }}
                @endscope

                {{-- Mobil Spalte --}}
                @scope('cell_mobile', $anmeldung)
                    {{ $anmeldung->mobile ?? '-' }}
                @endscope

                {{-- Status Spalte --}}
                @scope('cell_status', $anmeldung)
                    <div class="text-center">
                        @if ($anmeldung->ist_mitglied)
                            <x-badge value="Mitglied" class="badge-success" />
                        @else
                            <x-badge value="Gast" class="badge-info" />
                        @endif
                    </div>
                @endscope

                {{-- Orga-Team Spalte --}}
                @scope('cell_orga_team', $anmeldung)
                    <div class="text-center">
                        @if ($anmeldung->ist_mitglied)
                            <x-button
                                wire:click="toggleOrgaTeam({{ $anmeldung->id }})"
                                class="btn-xs {{ $anmeldung->orga_team ? 'btn-primary' : 'btn-ghost' }}"
                                aria-pressed="{{ $anmeldung->orga_team ? 'true' : 'false' }}"
                                aria-label="Orga-Team Status für {{ $anmeldung->full_name }} umschalten"
                            >
                                @if ($anmeldung->orga_team)
                                    <x-icon name="s-star" class="w-4 h-4" />
                                    <span>Im Orga-Team</span>
                                @else
                                    <x-icon name="o-star" class="w-4 h-4" />
                                    <span>Nicht im Orga-Team</span>
                                @endif
                            </x-button>
                        @else
                            <x-badge value="Nur Mitglieder" class="badge-ghost badge-sm" />
                        @endif
                    </div>
                @endscope

                {{-- T-Shirt Spalte --}}
                @scope('cell_tshirt', $anmeldung)
                    <div class="text-center">
                        @if ($anmeldung->tshirt_bestellt)
                            <div class="font-medium">{{ $anmeldung->tshirt_groesse }}</div>
                            <x-button 
                                wire:click="toggleTshirtFertig({{ $anmeldung->id }})"
                                class="btn-xs mt-1 {{ $anmeldung->tshirt_fertig ? 'btn-success' : 'btn-warning' }}"
                            >
                                {{ $anmeldung->tshirt_fertig ? '✓ Fertig' : 'Offen' }}
                            </x-button>
                        @else
                            <span class="opacity-40">-</span>
                        @endif
                    </div>
                @endscope

                {{-- Zahlung Spalte --}}
                @scope('cell_zahlung', $anmeldung)
                    <div class="text-center">
                        <div class="font-medium">{{ number_format($anmeldung->payment_amount, 2, ',', '.') }} €</div>
                        <div class="mt-1 flex flex-wrap justify-center gap-1">
                            @if ($anmeldung->payment_status === 'free')
                                <x-badge value="Kostenlos" class="badge-ghost badge-sm" />
                                @if ($anmeldung->orga_team)
                                    <x-badge value="Orga-Team" class="badge-primary badge-sm" />
                                @endif
                            @else
                                <x-button
                                    wire:click="toggleZahlungseingang({{ $anmeldung->id }})"
                                    class="btn-xs {{ $anmeldung->zahlungseingang ? 'btn-success' : 'btn-error' }}"
                                >
                                    {{ $anmeldung->zahlungseingang ? '✓ Erhalten' : 'Ausstehend' }}
                                </x-button>
                            @endif
                        </div>
                        @if ($anmeldung->paypal_transaction_id)
                            <div class="text-xs opacity-60 mt-1">
                                PayPal: {{ substr($anmeldung->paypal_transaction_id, 0, 12) }}...
                            </div>
                        @endif
                    </div>
                @endscope

                {{-- Profil Spalte --}}
                @scope('cell_profil', $anmeldung)
                    <div class="text-center">
                        @if ($anmeldung->user)
                            <x-button 
                                label="Profil"
                                link="{{ route('profile.view', $anmeldung->user) }}" 
                                class="btn-link btn-xs btn-primary"
                                external
                            />
                        @else
                            <span class="opacity-40">-</span>
                        @endif
                    </div>
                @endscope

                {{-- Aktionen Spalte --}}
                @scope('cell_actions', $anmeldung)
                    <div class="text-center">
                        <x-button 
                            wire:click="deleteAnmeldung({{ $anmeldung->id }})"
                            wire:confirm="Möchten Sie die Anmeldung von {{ $anmeldung->full_name }} wirklich löschen?"
                            icon="o-trash"
                            class="btn-ghost btn-xs text-error hover:btn-error"
                            tooltip="Anmeldung löschen"
                        />
                    </div>
                @endscope

                {{-- Empty State --}}
                <x-slot:empty>
                    <x-icon name="o-users" class="w-12 h-12 opacity-30 mx-auto" />
                    <p class="mt-2">Keine Anmeldungen gefunden.</p>
                </x-slot:empty>
            </x-table>

            {{-- Pagination --}}
            <x-slot:footer>
                <div class="p-4">
                    {{ $this->anmeldungen->links() }}
                </div>
            </x-slot:footer>
        </x-card>
    </div>
</div>

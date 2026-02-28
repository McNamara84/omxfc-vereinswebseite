<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-header title="Belohnungen - Admin" subtitle="Verwalte Belohnungen, Vergaberegeln und Freischaltungen" separator />

        {{-- Tabs --}}
        <x-tabs wire:model="activeTab">
            <x-tab name="rewards" label="Belohnungen" icon="o-gift">

                <div class="flex justify-end mb-4">
                    <x-button label="Neue Belohnung" icon="o-plus" class="btn-primary" wire:click="openCreateReward" />
                </div>

                <x-table :headers="[
                    ['key' => 'sort_order', 'label' => '#'],
                    ['key' => 'title', 'label' => 'Titel'],
                    ['key' => 'category', 'label' => 'Kategorie'],
                    ['key' => 'cost_baxx', 'label' => 'Preis (Baxx)'],
                    ['key' => 'purchase_count', 'label' => 'Käufe'],
                    ['key' => 'is_active', 'label' => 'Status'],
                    ['key' => 'actions', 'label' => 'Aktionen'],
                ]" :rows="$this->rewards" striped>

                    @scope('cell_is_active', $reward)
                        @if($reward->is_active)
                            <x-badge value="Aktiv" class="badge-success" />
                        @else
                            <x-badge value="Inaktiv" class="badge-ghost" />
                        @endif
                    @endscope

                    @scope('cell_actions', $reward)
                        <div class="flex gap-2">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs" wire:click="openEditReward({{ $reward->id }})" tooltip="Bearbeiten" />
                            <x-button
                                icon="{{ $reward->is_active ? 'o-eye-slash' : 'o-eye' }}"
                                class="btn-ghost btn-xs"
                                wire:click="toggleRewardActive({{ $reward->id }})"
                                tooltip="{{ $reward->is_active ? 'Deaktivieren' : 'Aktivieren' }}"
                            />
                        </div>
                    @endscope
                </x-table>

            </x-tab>

            <x-tab name="rules" label="Vergaberegeln" icon="o-cog-6-tooth">

                <x-alert icon="o-information-circle" class="mb-4 alert-info">
                    Hier kannst du festlegen, wie viele Baxx Mitglieder für bestimmte Aktionen erhalten.
                </x-alert>

                <x-table :headers="[
                    ['key' => 'action_key', 'label' => 'Aktion'],
                    ['key' => 'label', 'label' => 'Bezeichnung'],
                    ['key' => 'description', 'label' => 'Beschreibung'],
                    ['key' => 'points', 'label' => 'Baxx'],
                    ['key' => 'is_active', 'label' => 'Status'],
                    ['key' => 'actions', 'label' => 'Aktionen'],
                ]" :rows="$this->earningRules" striped>

                    @scope('cell_is_active', $rule)
                        @if($rule->is_active)
                            <x-badge value="Aktiv" class="badge-success" />
                        @else
                            <x-badge value="Inaktiv" class="badge-ghost" />
                        @endif
                    @endscope

                    @scope('cell_actions', $rule)
                        <div class="flex gap-2">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs" wire:click="openEditRule({{ $rule->id }})" tooltip="Bearbeiten" />
                            <x-button
                                icon="{{ $rule->is_active ? 'o-eye-slash' : 'o-eye' }}"
                                class="btn-ghost btn-xs"
                                wire:click="toggleRuleActive({{ $rule->id }})"
                                tooltip="{{ $rule->is_active ? 'Deaktivieren' : 'Aktivieren' }}"
                            />
                        </div>
                    @endscope
                </x-table>

            </x-tab>

            <x-tab name="purchases" label="Freischaltungen" icon="o-shopping-cart">

                {{-- Filter --}}
                <div class="flex flex-wrap gap-4 mb-4">
                    <x-input
                        wire:model.live.debounce.300ms="purchaseSearch"
                        placeholder="Mitglied suchen..."
                        icon="o-magnifying-glass"
                        class="w-64"
                    />
                    <x-select
                        wire:model.live="purchaseRewardFilter"
                        :options="array_merge(
                            [['id' => 'alle', 'name' => 'Alle Belohnungen']],
                            $this->rewards->map(fn($r) => ['id' => (string) $r->id, 'name' => $r->title])->toArray()
                        )"
                        option-value="id"
                        option-label="name"
                        class="w-64"
                    />
                </div>

                <x-table :headers="[
                    ['key' => 'user_name', 'label' => 'Mitglied'],
                    ['key' => 'reward_title', 'label' => 'Belohnung'],
                    ['key' => 'cost_baxx', 'label' => 'Kosten (Baxx)'],
                    ['key' => 'purchased_at', 'label' => 'Kaufdatum'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'actions', 'label' => 'Aktionen'],
                ]" :rows="$this->purchases" striped>

                    @scope('cell_user_name', $purchase)
                        {{ $purchase->user?->name ?? 'Gelöscht' }}
                    @endscope

                    @scope('cell_reward_title', $purchase)
                        {{ $purchase->reward?->title ?? 'Gelöscht' }}
                    @endscope

                    @scope('cell_purchased_at', $purchase)
                        {{ $purchase->purchased_at->format('d.m.Y H:i') }}
                    @endscope

                    @scope('cell_status', $purchase)
                        @if($purchase->isRefunded())
                            <x-badge value="Erstattet" class="badge-warning" />
                            <span class="text-xs text-base-content/60 block mt-1">
                                {{ $purchase->refunded_at->format('d.m.Y') }} von {{ $purchase->refundedByUser?->name ?? 'System' }}
                            </span>
                        @else
                            <x-badge value="Aktiv" class="badge-success" />
                        @endif
                    @endscope

                    @scope('cell_actions', $purchase)
                        @unless($purchase->isRefunded())
                            <x-button
                                icon="o-arrow-uturn-left"
                                label="Erstatten"
                                class="btn-ghost btn-xs text-error"
                                wire:click="refundPurchase({{ $purchase->id }})"
                                wire:confirm="Freischaltung rückgängig machen? {{ $purchase->user?->name }} erhält {{ $purchase->cost_baxx }} Baxx zurück."
                            />
                        @endunless
                    @endscope
                </x-table>

            </x-tab>

            <x-tab name="statistics" label="Statistiken" icon="o-chart-bar">

                {{-- Gesamtstatistiken --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <x-stat
                        title="Gesamt ausgegebene Baxx"
                        :value="$this->statistics['total_spent_baxx']"
                        icon="o-currency-dollar"
                        class="shadow"
                    />
                    <x-stat
                        title="Aktive Belohnungen"
                        :value="$this->statistics['rewards_stats']->count()"
                        icon="o-gift"
                        class="shadow"
                    />
                    <x-stat
                        title="Nie freigeschaltet"
                        :value="$this->statistics['never_purchased_rewards']->count()"
                        icon="o-exclamation-triangle"
                        class="shadow"
                        color="text-warning"
                    />
                </div>

                {{-- Pro-Feature Statistiken --}}
                <h3 class="text-lg font-bold text-primary mb-4">Belohnungen nach Popularität</h3>
                <x-table :headers="[
                    ['key' => 'title', 'label' => 'Belohnung'],
                    ['key' => 'category', 'label' => 'Kategorie'],
                    ['key' => 'cost_baxx', 'label' => 'Preis'],
                    ['key' => 'purchase_count', 'label' => 'Käufe'],
                    ['key' => 'total_baxx_earned', 'label' => 'Einnahmen (Baxx)'],
                ]" :rows="$this->statistics['rewards_stats']" striped>

                    @scope('cell_purchase_count', $reward)
                        @if($reward->purchase_count > 0)
                            <x-badge :value="$reward->purchase_count" class="badge-success" />
                        @else
                            <x-badge value="0" class="badge-ghost" />
                        @endif
                    @endscope

                    @scope('cell_total_baxx_earned', $reward)
                        {{ $reward->total_baxx_earned ?? 0 }}
                    @endscope
                </x-table>

                @if($this->statistics['never_purchased_rewards']->isNotEmpty())
                    <h3 class="text-lg font-bold text-warning mt-8 mb-4">Noch nie freigeschaltete Belohnungen</h3>
                    <div class="space-y-2">
                        @foreach($this->statistics['never_purchased_rewards'] as $reward)
                            <x-card shadow class="opacity-75">
                                <div class="flex items-center justify-between">
                                    <span>{{ $reward->title }}</span>
                                    <x-badge :value="$reward->cost_baxx . ' Baxx'" class="badge-ghost" />
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                @endif

            </x-tab>

            <x-tab name="downloads" label="Downloads" icon="o-arrow-down-tray">

                <div class="flex justify-end mb-4">
                    <x-button label="Neuer Download" icon="o-plus" class="btn-primary" wire:click="openCreateDownload" />
                </div>

                <x-table :headers="[
                    ['key' => 'sort_order', 'label' => '#'],
                    ['key' => 'title', 'label' => 'Titel'],
                    ['key' => 'category', 'label' => 'Kategorie'],
                    ['key' => 'original_filename', 'label' => 'Datei'],
                    ['key' => 'formatted_file_size', 'label' => 'Größe'],
                    ['key' => 'is_active', 'label' => 'Status'],
                    ['key' => 'linked_reward', 'label' => 'Verknüpfte Belohnung'],
                    ['key' => 'actions', 'label' => 'Aktionen'],
                ]" :rows="$this->downloads" striped>

                    @scope('cell_is_active', $download)
                        @if($download->is_active)
                            <x-badge value="Aktiv" class="badge-success" />
                        @else
                            <x-badge value="Inaktiv" class="badge-ghost" />
                        @endif
                    @endscope

                    @scope('cell_linked_reward', $download)
                        @if($download->reward)
                            <x-badge :value="$download->reward->title" class="badge-info badge-sm" />
                        @else
                            <span class="text-base-content/50 text-sm">Keine</span>
                        @endif
                    @endscope

                    @scope('cell_actions', $download)
                        <div class="flex gap-2">
                            <x-button icon="o-pencil" class="btn-ghost btn-xs" wire:click="openEditDownload({{ $download->id }})" tooltip="Bearbeiten" />
                            <x-button
                                icon="{{ $download->is_active ? 'o-eye-slash' : 'o-eye' }}"
                                class="btn-ghost btn-xs"
                                wire:click="toggleDownloadActive({{ $download->id }})"
                                tooltip="{{ $download->is_active ? 'Deaktivieren' : 'Aktivieren' }}"
                            />
                            <x-button
                                icon="o-trash"
                                class="btn-ghost btn-xs text-error"
                                wire:click="deleteDownload({{ $download->id }})"
                                wire:confirm="Download '{{ $download->title }}' wirklich löschen? Die Datei wird ebenfalls entfernt."
                                tooltip="Löschen"
                            />
                        </div>
                    @endscope
                </x-table>

            </x-tab>
        </x-tabs>

        {{-- Modal: Belohnung bearbeiten/erstellen --}}
        <x-modal wire:model="showRewardModal" title="{{ $editingRewardId ? 'Belohnung bearbeiten' : 'Neue Belohnung' }}">
            <div class="space-y-4">
                <x-input wire:model="rewardTitle" label="Titel" placeholder="Name der Belohnung" />
                <x-textarea wire:model="rewardDescription" label="Beschreibung" placeholder="Was wird freigeschaltet?" />
                <x-input wire:model="rewardCategory" label="Kategorie" placeholder="z.B. Statistiken, Downloads" />
                <x-input wire:model="rewardCostBaxx" label="Preis (Baxx)" type="number" min="1" />
                <x-input wire:model="rewardSortOrder" label="Sortierung" type="number" min="0" />
                <x-toggle wire:model="rewardIsActive" label="Aktiv" />

                <x-select
                    wire:model="rewardDownloadId"
                    label="Verknüpfter Download (optional)"
                    placeholder="Keinen Download verknüpfen"
                    :options="$this->downloads->map(fn($d) => ['id' => $d->id, 'name' => $d->title . ' (' . $d->category . ')'])->toArray()"
                    option-value="id"
                    option-label="name"
                    placeholder-value=""
                />
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="$set('showRewardModal', false)" />
                <x-button label="Speichern" class="btn-primary" wire:click="saveReward" />
            </x-slot:actions>
        </x-modal>

        {{-- Modal: Vergaberegel bearbeiten --}}
        <x-modal wire:model="showRuleModal" title="Vergaberegel bearbeiten">
            <div class="space-y-4">
                <x-input wire:model="ruleLabel" label="Bezeichnung" />
                <x-textarea wire:model="ruleDescription" label="Beschreibung" />
                <x-input wire:model="rulePoints" label="Baxx-Betrag" type="number" min="0" />
                <x-toggle wire:model="ruleIsActive" label="Aktiv" />
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="$set('showRuleModal', false)" />
                <x-button label="Speichern" class="btn-primary" wire:click="saveRule" />
            </x-slot:actions>
        </x-modal>

        {{-- Modal: Download bearbeiten/erstellen --}}
        <x-modal wire:model="showDownloadModal" title="{{ $editingDownloadId ? 'Download bearbeiten' : 'Neuer Download' }}">
            <div class="space-y-4">
                <x-input wire:model="downloadTitle" label="Titel" placeholder="Name des Downloads" />
                <x-textarea wire:model="downloadDescription" label="Beschreibung (optional)" placeholder="Beschreibung des Downloads" />
                <x-input wire:model="downloadCategory" label="Kategorie" placeholder="z.B. Klemmbaustein-Anleitungen, Fanstories" />
                <x-input wire:model="downloadSortOrder" label="Sortierung" type="number" min="0" />
                <x-toggle wire:model="downloadIsActive" label="Aktiv" />

                <div>
                    <label class="label">
                        <span class="label-text font-semibold">Datei{{ $editingDownloadId ? ' (optional – ersetzt bestehende)' : '' }}</span>
                    </label>
                    <input type="file" wire:model="downloadFile" accept=".pdf,.zip,.epub" class="file-input file-input-bordered w-full" />
                    @error('downloadFile') <span class="text-error text-sm">{{ $message }}</span> @enderror

                    <div wire:loading wire:target="downloadFile" class="text-sm text-info mt-1">
                        Datei wird hochgeladen...
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="$set('showDownloadModal', false)" />
                <x-button label="Speichern" class="btn-primary" wire:click="saveDownload" />
            </x-slot:actions>
        </x-modal>
    </div>
</div>

<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Maddraxikon-Baxx"
            description="Überwache OAuth-Verknüpfungen, Wiki-Import und Baxx-Vergaben. Alle angezeigten Daten stammen aus der lokalen Vereinsdatenbank."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <x-button
                        label="Zur Belohnungsverwaltung"
                        :link="route('rewards.admin')"
                        wire:navigate
                        icon="o-arrow-left"
                        class="btn-ghost"
                    />
                    <x-button
                        label="Wiki öffnen"
                        :link="$wikiBaseUrl"
                        external
                        icon="o-arrow-top-right-on-square"
                        class="btn-ghost"
                    />
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel
            title="Betriebszustand"
            description="Die Schalter werden ausschließlich über die Serverkonfiguration geändert."
        >
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($featureSwitches as $feature)
                    <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                        <p class="text-sm text-base-content/65">{{ $feature['label'] }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span
                                @class([
                                    'size-2.5 rounded-full',
                                    'bg-success' => $feature['enabled'],
                                    'bg-base-content/30' => ! $feature['enabled'],
                                ])
                            ></span>
                            <span class="font-semibold">
                                {{ $feature['enabled'] ? 'Aktiviert' : 'Deaktiviert' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-base-200 pt-5">
                <x-button
                    label="Synchronisation einreihen"
                    icon="o-arrow-path"
                    class="btn-primary"
                    wire:click="dispatchSync"
                    wire:loading.attr="disabled"
                    wire:target="dispatchSync"
                    :disabled="! $featureSwitches['sync']['enabled']"
                />
                <x-button
                    label="Baxx-Auswertung einreihen"
                    icon="o-gift"
                    class="btn-secondary"
                    wire:click="dispatchEvaluation"
                    wire:loading.attr="disabled"
                    wire:target="dispatchEvaluation"
                    :disabled="! $featureSwitches['awards']['enabled'] || $syncState?->recovery_required_at"
                />
                <span class="text-xs text-base-content/55">
                    Wiki: {{ $wikiKey }}
                </span>
            </div>
        </x-ui.panel>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel
                title="Synchronisationsstatus"
                description="Lokaler Watermark und Ergebnis des letzten Importlaufs."
            >
                @if ($syncState)
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-base-content/60">Watermark</dt>
                            <dd class="mt-1 font-medium">
                                {{ $syncState->watermark_at?->copy()->timezone($timezone)->format('d.m.Y H:i:s') ?? '–' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-base-content/60">Letzter Erfolg</dt>
                            <dd class="mt-1 font-medium">
                                {{ $syncState->last_succeeded_at?->copy()->timezone($timezone)->format('d.m.Y H:i:s') ?? '–' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-base-content/60">Zuletzt importiert</dt>
                            <dd class="mt-1 font-medium">{{ $syncState->last_imported_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-base-content/60">Fehler in Folge</dt>
                            <dd class="mt-1 font-medium">{{ $syncState->consecutive_failures }}</dd>
                        </div>
                    </dl>

                    @if ($syncState->recovery_required_at)
                        <x-alert icon="o-exclamation-triangle" class="mt-5 alert-error">
                            <p class="font-semibold">Recovery erforderlich – Baxx-Auswertung gesperrt</p>
                            <p class="mt-1 text-sm">
                                Offenes Fenster:
                                {{ $syncState->recovery_from_at?->copy()->timezone($timezone)->format('d.m.Y H:i:s') ?? 'unbekannt' }}
                                bis
                                {{ $syncState->recovery_until_at?->copy()->timezone($timezone)->format('d.m.Y H:i:s') ?? 'unbekannt' }}.
                                Starte nach Prüfung den expliziten Befehl
                                <code>php artisan maddraxikon:recover</code>.
                            </p>
                        </x-alert>
                    @endif

                    @if ($syncState->last_error)
                        <x-alert icon="o-exclamation-triangle" class="mt-5 alert-error">
                            <p class="font-semibold">Letzter Importfehler</p>
                            <p class="mt-1 break-words text-sm">{{ $syncState->last_error }}</p>
                        </x-alert>
                    @endif
                @else
                    <x-alert icon="o-information-circle" class="alert-info">
                        Die Synchronisation wurde noch nicht initialisiert.
                    </x-alert>
                @endif
            </x-ui.panel>

            <x-ui.panel
                title="Namensraum-Prüfung"
                description="Diese Prüfung ist der einzige Vorgang auf dieser Seite, der das Maddraxikon direkt abfragt."
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-base-content/65">
                        Vergleicht die erlaubten IDs und lokalisierten Namen mit der Wiki-Konfiguration.
                    </p>
                    <x-button
                        label="Jetzt prüfen"
                        icon="o-shield-check"
                        class="btn-outline"
                        wire:click="checkNamespaces"
                        spinner="checkNamespaces"
                    />
                </div>

                @if ($namespaceHealthError)
                    <x-alert icon="o-exclamation-triangle" class="mt-5 alert-error">
                        {{ $namespaceHealthError }}
                    </x-alert>
                @elseif ($namespaceHealth)
                    <x-alert
                        icon="{{ $namespaceHealth['healthy'] ? 'o-check-circle' : 'o-exclamation-triangle' }}"
                        class="mt-5 {{ $namespaceHealth['healthy'] ? 'alert-success' : 'alert-warning' }}"
                    >
                        {{ $namespaceHealth['healthy']
                            ? 'Alle erwarteten Namensräume stimmen überein.'
                            : 'Es wurden Namensraum-Abweichungen gefunden.' }}
                    </x-alert>

                    <div class="mt-4 overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Erwartet</th>
                                    <th>Gefunden</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($namespaceHealth['expected'] as $namespaceId => $expectedName)
                                    @php
                                        $actualName = $namespaceHealth['actual'][$namespaceId] ?? null;
                                        $missing = array_key_exists($namespaceId, $namespaceHealth['missing']);
                                        $mismatched = array_key_exists(
                                            $namespaceId,
                                            $namespaceHealth['mismatched'],
                                        );
                                    @endphp
                                    <tr>
                                        <td class="font-mono">{{ $namespaceId }}</td>
                                        <td>{{ $expectedName !== '' ? $expectedName : '(Hauptnamensraum)' }}</td>
                                        <td>
                                            @if ($actualName === null)
                                                –
                                            @else
                                                {{ $actualName !== '' ? $actualName : '(Hauptnamensraum)' }}
                                            @endif
                                        </td>
                                        <td>
                                            <x-badge
                                                :value="$missing ? 'Fehlt' : ($mismatched ? 'Abweichend' : 'OK')"
                                                class="{{ $missing || $mismatched ? 'badge-warning' : 'badge-success' }} badge-sm"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.panel>
        </div>

        <x-ui.panel
            title="Beitragsstatus"
            description="Anzahl der lokal gespeicherten Beiträge je Verarbeitungsstatus."
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($contributionStatusLabels as $status => $label)
                    <x-stat
                        :title="$label"
                        :value="$contributionCounts[$status]"
                        icon="o-document-text"
                        class="border border-base-300 shadow-none"
                        data-testid="maddraxikon-contribution-count-{{ $status }}"
                    />
                @endforeach
                <x-stat
                    title="Technische Fehler"
                    :value="$technicalFailureCount"
                    icon="o-exclamation-triangle"
                    class="border border-base-300 shadow-none"
                    data-testid="maddraxikon-technical-failure-count"
                />
            </div>
        </x-ui.panel>

        <x-ui.panel
            title="Importierte Beiträge"
            description="Fachliche Ablehnungen und technische Prüfprobleme werden getrennt ausgewiesen. Technische Fehler können gezielt erneut geprüft werden."
        >
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <label class="form-control w-full max-w-xs">
                    <span class="label-text mb-1 text-sm">Status filtern</span>
                    <select
                        class="select select-bordered"
                        wire:model.live="contributionStatusFilter"
                        data-testid="maddraxikon-contribution-filter"
                    >
                        <option value="all">Alle</option>
                        @foreach ($contributionStatusLabels as $status => $label)
                            <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                        <option value="technical">Technischer Fehler</option>
                    </select>
                </label>
                <p class="text-xs text-base-content/55">Maximal 50 neueste Treffer</p>
            </div>

            @if ($recentContributions->isEmpty())
                <p class="py-6 text-center text-sm text-base-content/60">
                    Keine Beiträge für diesen Filter vorhanden.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Mitglied / Wiki</th>
                                <th>Seite</th>
                                <th>Zeitpunkt</th>
                                <th>Status</th>
                                <th class="text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentContributions as $contribution)
                                @php
                                    $diffUrl = rtrim($wikiBaseUrl, '/')
                                        . '/index.php?title=Special%3ADiff%2F'
                                        . $contribution->revision_id;
                                @endphp
                                <tr wire:key="maddraxikon-contribution-{{ $contribution->id }}">
                                    <td>
                                        <span class="font-medium">
                                            {{ $contribution->user?->name ?? 'Gelöschtes Mitglied' }}
                                        </span>
                                        <span class="block text-xs text-base-content/55">
                                            {{ $contribution->accountLink?->wiki_username ?? $contribution->wiki_username }}
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="{{ $diffUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="link link-primary font-medium"
                                        >
                                            {{ $contribution->page_title }}
                                        </a>
                                        <span class="block text-xs text-base-content/55">
                                            Revision {{ $contribution->revision_id }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $contribution->occurredAtUtc()->setTimezone($timezone)->format('d.m.Y H:i') }}
                                    </td>
                                    <td>
                                        <x-badge
                                            :value="$contributionStatusLabels[$contribution->status->value]"
                                            class="badge-outline badge-sm"
                                        />
                                        @if ($contribution->last_evaluation_error)
                                            <span class="mt-1 block max-w-80 text-xs text-error">
                                                Technisch fehlgeschlagen
                                                (Versuch {{ $contribution->evaluation_attempts }}):
                                                {{ $contribution->last_evaluation_error }}
                                            </span>
                                        @elseif ($contribution->status_reason)
                                            <span class="mt-1 block max-w-80 text-xs text-base-content/55">
                                                {{ $contribution->status_reason }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if (
                                            $contribution->status === \App\Enums\MaddraxikonContributionStatus::Pending
                                            && $contribution->last_evaluation_error_at
                                            && $contribution->eligible_after->isPast()
                                        )
                                            <x-button
                                                label="Erneut prüfen"
                                                icon="o-arrow-path"
                                                class="btn-outline btn-xs"
                                                wire:click="retryContribution({{ $contribution->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="retryContribution({{ $contribution->id }})"
                                            />
                                        @else
                                            <span class="text-xs text-base-content/40">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel
            title="Letzte Kontoverknüpfungen"
            description="Zuletzt geänderte Maddraxikon-Zuordnungen. OAuth-Kennungen und Tokens werden hier nicht angezeigt."
        >
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <label class="form-control w-full max-w-xs">
                    <span class="label-text mb-1 text-sm">Status filtern</span>
                    <select
                        class="select select-bordered"
                        wire:model.live="linkStatusFilter"
                        data-testid="maddraxikon-link-filter"
                    >
                        <option value="all">Alle</option>
                        @foreach ($linkStatusLabels as $status => $label)
                            <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="text-sm">
                    Aktive Verknüpfungen:
                    <strong data-testid="maddraxikon-active-link-count">
                        {{ $activeLinkCount }}
                    </strong>
                </p>
            </div>

            @if ($recentLinks->isEmpty())
                <p class="py-6 text-center text-sm text-base-content/60">
                    Keine Kontoverknüpfungen für diesen Filter vorhanden.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Vereinsmitglied</th>
                                <th>Maddraxikon</th>
                                <th>Status</th>
                                <th>Bestätigt</th>
                                <th>Getrennt</th>
                                <th class="text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLinks as $link)
                                <tr wire:key="maddraxikon-link-{{ $link->id }}">
                                    <td>{{ $link->user?->name ?? 'Gelöschtes Mitglied' }}</td>
                                    <td>
                                        <span class="font-medium">{{ $link->wiki_username }}</span>
                                        <span class="block text-xs text-base-content/55">
                                            Wiki-ID {{ $link->wiki_user_id }}
                                        </span>
                                    </td>
                                    <td>
                                        <x-badge
                                            :value="$linkStatusLabels[$link->status->value]"
                                            class="{{ $link->isActive() ? 'badge-success' : 'badge-ghost' }} badge-sm"
                                        />
                                    </td>
                                    <td>
                                        {{ $link->verified_at?->copy()->timezone($timezone)->format('d.m.Y H:i') ?? '–' }}
                                    </td>
                                    <td>
                                        {{ $link->disconnected_at?->copy()->timezone($timezone)->format('d.m.Y H:i') ?? '–' }}
                                    </td>
                                    <td class="text-right">
                                        @if ($link->status === \App\Enums\MaddraxikonAccountLinkStatus::Disconnected)
                                            <x-button
                                                label="Fehlzuordnung korrigieren"
                                                class="btn-warning btn-outline btn-xs"
                                                wire:click="openLinkCorrection({{ $link->id }})"
                                                data-testid="maddraxikon-open-link-correction-{{ $link->id }}"
                                            />
                                        @else
                                            <span class="text-xs text-base-content/40">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($recentLinkCorrections->isNotEmpty())
                <div class="mt-6 border-t border-base-300 pt-5">
                    <h3 class="font-semibold">Letzte Zuordnungskorrekturen</h3>
                    <p class="mb-3 text-xs text-base-content/55">
                        Unveränderliches Audit der dauerhaft gesperrten Altidentitäten.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mitglied / alte Identität</th>
                                    <th>Wiki-ID</th>
                                    <th>Korrektur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentLinkCorrections as $correction)
                                    <tr wire:key="maddraxikon-link-correction-{{ $correction->id }}">
                                        <td>
                                            {{ $correction->affectedUser?->name ?? 'Gelöschtes Mitglied' }}
                                            <span class="block text-xs text-base-content/55">
                                                {{ $correction->old_wiki_username }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $correction->old_wiki_user_id }}
                                        </td>
                                        <td>
                                            {{ $correction->actor?->name ?? 'Gelöschter Admin' }}
                                            <span class="block text-xs text-base-content/55">
                                                {{ $correction->corrected_at->copy()->timezone($timezone)->format('d.m.Y H:i') }}
                                            </span>
                                            <span class="mt-1 block max-w-96 text-xs">
                                                {{ $correction->reason }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel
            title="Letzte Baxx-Ereignisse"
            description="Unveränderliches Vergabeprotokoll einschließlich Deckelung und administrativer Gegenbuchungen."
        >
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <label class="form-control w-full max-w-xs">
                    <span class="label-text mb-1 text-sm">Status filtern</span>
                    <select
                        class="select select-bordered"
                        wire:model.live="rewardStatusFilter"
                        data-testid="maddraxikon-reward-filter"
                    >
                        <option value="all">Alle</option>
                        @foreach ($rewardStatusLabels as $status => $label)
                            <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="text-xs text-base-content/55">Maximal 50 neueste Treffer</p>
            </div>

            @if ($recentRewardEvents->isEmpty())
                <p class="py-6 text-center text-sm text-base-content/60">
                    Keine Baxx-Ereignisse für diesen Filter vorhanden.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Mitglied / Wiki</th>
                                <th>Aktion</th>
                                <th>Aktivität</th>
                                <th>Baxx</th>
                                <th>Status</th>
                                <th class="text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentRewardEvents as $event)
                                <tr wire:key="maddraxikon-reward-{{ $event->id }}">
                                    <td>
                                        <span class="font-medium">
                                            {{ $event->user?->name ?? 'Gelöschtes Mitglied' }}
                                        </span>
                                        <span class="block text-xs text-base-content/55">
                                            {{ $event->accountLink?->wiki_username ?? 'Verknüpfung gelöscht' }}
                                        </span>
                                    </td>
                                    @php
                                        $rewardDiffUrl = rtrim($wikiBaseUrl, '/')
                                            . '/index.php?title=Special%3ADiff%2F'
                                            . $event->source_revision_id;
                                    @endphp
                                    <td>
                                        {{ $rewardActionLabels[$event->action_key] ?? $event->action_key }}
                                        <a
                                            href="{{ $rewardDiffUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="link link-primary block text-xs"
                                        >
                                            Revision {{ $event->source_revision_id }}
                                        </a>
                                    </td>
                                    <td>{{ $event->activity_date->format('d.m.Y') }}</td>
                                    <td>
                                        <span class="font-semibold">{{ $event->awarded_points }}</span>
                                        @if ($event->capped_points > 0)
                                            <span class="block text-xs text-warning">
                                                {{ $event->capped_points }} gedeckelt
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-badge
                                            :value="$rewardStatusLabels[$event->status->value]"
                                            class="badge-outline badge-sm"
                                        />
                                        @if ($event->status_reason)
                                            <span class="mt-1 block max-w-64 text-xs text-base-content/55">
                                                {{ $event->status_reason }}
                                            </span>
                                        @endif
                                        @if ($event->status === \App\Enums\MaddraxikonRewardEventStatus::Reversed)
                                            <span class="mt-1 block max-w-64 text-xs text-base-content/55">
                                                {{ $event->reversal_reason }}
                                                @if ($event->reversedBy)
                                                    – {{ $event->reversedBy->name }}
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if (
                                            $event->status === \App\Enums\MaddraxikonRewardEventStatus::Awarded
                                            && $event->awarded_points > 0
                                            && $event->reversal_user_point_id === null
                                        )
                                            <x-button
                                                label="Gegenbuchen"
                                                icon="o-arrow-uturn-left"
                                                class="btn-error btn-outline btn-xs"
                                                wire:click="openReversal({{ $event->id }})"
                                            />
                                        @else
                                            <span class="text-xs text-base-content/40">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>

        <x-modal
            wire:model="showLinkCorrectionModal"
            title="Fehlzuordnung dauerhaft korrigieren"
        >
            <div class="space-y-4">
                <x-alert icon="o-exclamation-triangle" class="alert-warning">
                    Die bisherige Maddraxikon-Identität wird dauerhaft gesperrt und die lokale Verknüpfung gelöscht. Beiträge und Baxx-Audit bleiben mit ihren gespeicherten Daten erhalten.
                </x-alert>
                <p class="text-sm text-base-content/70">
                    Es wird keine neue Wiki-Identität manuell eingetragen. Das betroffene Mitglied muss anschließend im Profil eine neue Verknüpfung per OAuth bestätigen.
                </p>
                <p class="text-sm text-base-content/70">
                    Bitte nur den erforderlichen Sachverhalt eintragen – keine E-Mailadressen, Zugangsdaten oder OAuth-Token (maximal 500 Zeichen).
                </p>
                <x-textarea
                    wire:model="linkCorrectionReason"
                    label="Begründung *"
                    placeholder="Warum war die bisherige Zuordnung falsch?"
                    rows="4"
                    data-testid="maddraxikon-link-correction-reason"
                />
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="cancelLinkCorrection" />
                <x-button
                    label="Altidentität sperren und freigeben"
                    class="btn-warning"
                    wire:click="correctAccountLink"
                    spinner="correctAccountLink"
                />
            </x-slot:actions>
        </x-modal>

        <x-modal
            wire:model="showReversalModal"
            title="Maddraxikon-Baxx gegenbuchen"
        >
            <div class="space-y-4">
                <x-alert icon="o-exclamation-triangle" class="alert-warning">
                    Die ursprüngliche Gutschrift bleibt im Audit-Protokoll erhalten. Es wird eine separate negative Baxx-Buchung angelegt.
                </x-alert>
                <x-textarea
                    wire:model="reversalReason"
                    label="Begründung *"
                    placeholder="Warum ist diese Gegenbuchung erforderlich?"
                    rows="4"
                />
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="cancelReversal" />
                <x-button
                    label="Verbindlich gegenbuchen"
                    icon="o-arrow-uturn-left"
                    class="btn-error"
                    wire:click="reverseRewardEvent"
                    spinner="reverseRewardEvent"
                />
            </x-slot:actions>
        </x-modal>
    </div>
</div>

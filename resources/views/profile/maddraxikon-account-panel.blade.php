<div class="space-y-6">
    @if (session('maddraxikon_status'))
        <x-alert icon="o-check-circle" class="alert-success" role="status" dismissible>
            {{ session('maddraxikon_status') }}
        </x-alert>
    @endif

    @if (session('maddraxikon_error'))
        <x-alert icon="o-exclamation-triangle" class="alert-error" role="alert" dismissible>
            {{ session('maddraxikon_error') }}
        </x-alert>
    @endif

    @if ($link?->isActive())
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge value="Verifiziert" class="badge-success" />
                    <a
                        href="{{ rtrim(config('maddraxikon.base_url'), '/') }}/index.php?title=Benutzer:{{ rawurlencode(str_replace(' ', '_', $link->wiki_username)) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold link link-primary"
                    >
                        {{ $link->wiki_username }}
                    </a>
                </div>

                <p class="text-sm leading-relaxed text-base-content/70">
                    Verknüpft seit
                    {{ $link->verified_at
                        ->setTimezone(config('maddraxikon.timezone', 'Europe/Berlin'))
                        ->locale('de')
                        ->isoFormat('D. MMMM YYYY, HH:mm [Uhr]') }}.
                    Nur Beiträge ab diesem Zeitpunkt können Baxx erhalten.
                </p>
                <p class="text-sm leading-relaxed text-base-content/70">
                    Änderungen werden nach {{ $rewardPolicy['evaluation_delay_hours'] }} Stunden geprüft.
                    @if ($rewardPolicy['edit']['is_active'])
                        @if ($rewardPolicy['mode'] === 'byte_tier')
                            Bearbeitungen derselben Seite werden zu 30-Minuten-Sitzungen zusammengefasst und nach ihrem Netto-Zuwachs bewertet:
                            @foreach ($rewardPolicy['edit']['tiers'] as $tier)
                                ab {{ number_format($tier['minimum_added_bytes'], 0, ',', '.') }} Bytes {{ $tier['points'] }} Baxx{{ $loop->last ? '.' : ',' }}
                            @endforeach
                        @elseif ($rewardPolicy['edit']['every_count'] === 1)
                            Eine qualifizierte Bearbeitungssitzung ergibt {{ $rewardPolicy['edit']['points'] }} Baxx.
                        @else
                            {{ $rewardPolicy['edit']['every_count'] }} qualifizierte Bearbeitungssitzungen ergeben {{ $rewardPolicy['edit']['points'] }} Baxx.
                        @endif
                    @else
                        Bearbeitungssitzungen werden aktuell nicht mit Baxx belohnt.
                    @endif
                    @if ($rewardPolicy['new_article']['is_active'])
                        Ein neuer Artikel mit mindestens {{ $rewardPolicy['minimum_article_bytes'] }} Byte ergibt {{ $rewardPolicy['new_article']['points'] }} Baxx.
                    @else
                        Neue Artikel werden aktuell nicht mit Baxx belohnt.
                    @endif
                    Pro Aktivitätstag werden höchstens {{ $rewardPolicy['daily_point_cap'] }} Baxx gutgeschrieben.
                </p>
                @if ($rewardPolicy['next'])
                    <div class="space-y-1 text-sm leading-relaxed text-base-content/70">
                        <p>
                            Die Regel „{{ $rewardPolicy['next']['name'] }}“ gilt ab
                            {{ $rewardPolicy['next']['effective_from']->setTimezone(config('maddraxikon.timezone', 'Europe/Berlin'))->locale('de')->isoFormat('D. MMMM YYYY, HH:mm [Uhr]') }}.
                        </p>
                        <p>
                            @if ($rewardPolicy['next']['edit']['is_active'])
                                Bearbeitungssitzungen werden dann nach folgenden Stufen bewertet:
                                @foreach ($rewardPolicy['next']['edit']['tiers'] as $tier)
                                    ab {{ number_format($tier['minimum_added_bytes'], 0, ',', '.') }} Bytes {{ $tier['points'] }} Baxx{{ $loop->last ? '.' : ',' }}
                                @endforeach
                            @else
                                Bearbeitungssitzungen werden dann nicht mit Baxx belohnt.
                            @endif
                            @if ($rewardPolicy['next']['new_article']['is_active'])
                                Neue Artikel erhalten dann ab {{ number_format($rewardPolicy['next']['new_article']['minimum_article_bytes'], 0, ',', '.') }} Byte {{ $rewardPolicy['next']['new_article']['points'] }} Baxx.
                            @else
                                Neue Artikel werden dann nicht mit Baxx belohnt.
                            @endif
                        </p>
                    </div>
                @endif
                <p class="text-sm leading-relaxed text-base-content/70">
                    Ob dein verifizierter Maddraxikon-Benutzername für andere Mitglieder sichtbar ist,
                    steuerst du separat unter „Kontaktfreigabe“.
                </p>
            </div>

            <form method="POST" action="{{ route('maddraxikon.oauth.disconnect') }}">
                @csrf
                @method('DELETE')
                <x-button
                    type="submit"
                    label="Verbindung trennen"
                    icon="o-link-slash"
                    class="btn-outline btn-error"
                />
            </form>
        </div>

        <x-alert icon="o-information-circle" class="alert-info" role="note">
            Beim Trennen enden zukünftige Gutschriften sofort. Bereits
            gutgeschriebene Baxx bleiben bestehen. Persönliche Maddraxikon-
            Bewertungen werden an deinen Rezensionen sofort ausgeblendet und
            die Profilfreigabe wird deaktiviert. Eine spätere Reaktivierung
            erfordert eine neue Anmeldung im Maddraxikon und eine erneute
            bewusste Profilfreigabe.
        </x-alert>

        @if (! $eligible)
            <x-alert icon="o-exclamation-triangle" class="alert-warning" role="status">
                Deine Vereinsberechtigung ist derzeit nicht aktiv. Die bestehende
                Verbindung kannst du trotzdem jederzeit trennen; neue Beiträge
                werden bis zur erneuten Berechtigung nicht mit Baxx belohnt.
            </x-alert>
        @endif
    @elseif (! $eligible)
        <x-alert icon="o-information-circle" class="alert-info" role="status">
            Die Baxx-Verknüpfung steht aktiven Vereinsmitgliedern zur Verfügung.
        </x-alert>
    @else
        @if ($link)
            <x-alert icon="o-link-slash" class="alert-warning" role="status">
                Die frühere Verbindung mit <strong>{{ $link->wiki_username }}</strong>
                ist getrennt. Für eine Reaktivierung ist eine neue
                OAuth-Bestätigung desselben Maddraxikon-Kontos erforderlich.
            </x-alert>
        @endif

        <div class="space-y-4">
            <p class="text-sm leading-relaxed text-base-content/75">
                Verbinde freiwillig dein Maddraxikon-Konto, damit zukünftige,
                qualifizierte Wiki-Beiträge deinem Vereinskonto zugeordnet und
                mit Baxx belohnt werden können. Wenn du Rezensionen auf der
                Vereinswebsite veröffentlichst, wird deine jeweils aktuelle
                persönliche Romanbewertung aus dem Maddraxikon automatisch im
                geschützten Rezensionenbereich angezeigt. Die Bewertung wird
                ungefähr alle 15 Minuten aktualisiert. Beim OAuth-Profilabruf werden
                die vom Wiki gelieferten Identitätsdaten einschließlich
                Subject-ID, Benutzername und Sperrstatus nur kurzzeitig
                verarbeitet. Der Benutzername wird zusätzlich über die
                öffentliche Action API bestätigt; dabei werden lokale
                Wiki-Nutzer-ID, kanonischer Benutzername und Sperrstatus
                kurzzeitig abgerufen. Dauerhaft gespeichert werden die
                OAuth-Subject-ID, die lokale Wiki-Nutzer-ID und der kanonische
                Benutzername. Wiki-E-Mailadresse, Sperrstatus sowie
                OAuth-Zugriffs- oder Aktualisierungstoken werden nicht
                dauerhaft gespeichert.
            </p>

            @if ($linkingEnabled)
                <form method="POST" action="{{ route('maddraxikon.oauth.start') }}" class="space-y-4">
                    @csrf

                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            id="maddraxikon-consent"
                            type="checkbox"
                            name="consent"
                            value="1"
                            required
                            class="checkbox checkbox-primary mt-0.5"
                        >
                        <span class="text-sm leading-relaxed text-base-content/75">
                            Ich stimme dem kurzzeitigen Identitäts- und
                            Sperrstatusabruf, der dauerhaften Speicherung von
                            OAuth-Subject-ID, lokaler Wiki-Nutzer-ID und
                            kanonischem Benutzernamen sowie der Verarbeitung
                            meiner öffentlichen Maddraxikon-Beiträge für das
                            Baxx-Belohnungssystem zu. Ich stimme außerdem dem
                            lesenden Abruf und der lokalen Zwischenspeicherung
                            meiner persönlichen Romanbewertungen sowie ihrer
                            automatischen Anzeige an meinen Vereinsrezensionen
                            für eingeloggte, berechtigte Mitglieder zu. Die Hinweise in der
                            <a href="{{ route('datenschutz') }}" class="link link-primary">Datenschutzerklärung</a>
                            habe ich zur Kenntnis genommen.
                        </span>
                    </label>

                    @error('consent')
                        <p class="text-sm text-error" role="alert">{{ $message }}</p>
                    @enderror

                    <x-button
                        type="submit"
                        label="Mit Maddraxikon verbinden"
                        icon="o-link"
                        class="btn-primary"
                    />
                </form>
            @else
                <x-alert icon="o-wrench-screwdriver" class="alert-warning" role="status">
                    Neue Maddraxikon-Verknüpfungen sind momentan noch nicht freigeschaltet.
                </x-alert>
            @endif
        </div>
    @endif

    @if ($link || $contributions->isNotEmpty())
        <div class="border-t border-base-content/10 pt-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-base-content">Deine erfassten Beiträge</h3>
                    <p class="mt-1 text-sm text-base-content/65">
                        Angezeigt werden deine neuesten 20 importierten Wiki-Ereignisse.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach ($statusLabels as $status => $label)
                        <x-badge
                            :value="$label.': '.($counts[$status] ?? 0)"
                            class="{{ $statusClasses[$status] }}"
                        />
                    @endforeach
                </div>
            </div>

            @if ($contributions->isEmpty())
                <p class="mt-4 rounded-2xl bg-base-200/60 p-4 text-sm text-base-content/65">
                    Seit der Verknüpfung wurden noch keine passenden Beiträge importiert.
                </p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Beitrag</th>
                                <th>Art</th>
                                <th>Zeitpunkt</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contributions as $contribution)
                                @php
                                    $status = $contribution->status->value;
                                    $diffUrl = rtrim(config('maddraxikon.base_url'), '/')
                                        .'/index.php?diff='.$contribution->revision_id;
                                @endphp
                                <tr wire:key="maddraxikon-contribution-{{ $contribution->id }}">
                                    <td>
                                        <a
                                            href="{{ $diffUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="link link-primary"
                                        >
                                            {{ $contribution->page_title }}
                                        </a>
                                    </td>
                                    <td>
                                        {{ $contribution->type->value === 'new' ? 'Neuer Artikel' : 'Bearbeitung' }}
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{
                                            $contribution->occurredAtUtc()
                                                ->setTimezone(config('maddraxikon.timezone', 'Europe/Berlin'))
                                                ->locale('de')
                                                ->isoFormat('D. MMM YYYY, HH:mm')
                                        }}
                                    </td>
                                    <td>
                                        <x-badge
                                            :value="$statusLabels[$status] ?? 'Unbekannt'"
                                            class="{{ $statusClasses[$status] ?? 'badge-outline' }}"
                                        />
                                        @if ($event = $contribution->rewardEvents->first())
                                            @if ($event->measured_added_bytes !== null)
                                                <span class="mt-1 block text-xs text-base-content/55">
                                                    Netto +{{ number_format($event->measured_added_bytes, 0, ',', '.') }} Bytes
                                                    @if ($event->matched_minimum_added_bytes !== null)
                                                        · Stufe ab {{ number_format($event->matched_minimum_added_bytes, 0, ',', '.') }}
                                                    @endif
                                                </span>
                                            @endif
                                            <span class="block text-xs text-base-content/55">
                                                {{ $event->awarded_points }} Baxx
                                                @if ($event->status_reason)
                                                    · {{ $rewardReasonLabels[$event->status_reason] ?? 'Prüfgrund nicht näher angegeben' }}
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

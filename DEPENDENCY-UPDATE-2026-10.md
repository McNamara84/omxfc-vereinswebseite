# Abhängigkeitsupdate und Feature-Nutzung vom 10. Oktober 2026

Ausgangsstand: `8f293705d2bb57c633f5410345b1d094ca76fe1a`. Die Versionsrecherche und die vollständige Matrix der 54 direkten Pakete stehen im [Implementierungsplan](IMPLEMENTIERUNGSPLAN_ABHAENGIGKEITEN_2026.md). Dieser Bericht beschreibt die Umsetzung. Nextcloud und die dazugehörigen persönlichen Notizen sind ausgeschlossen.

## Versionsstand und Sicherheitskorrekturen

| Bereich | Umgesetzter Stand |
| --- | --- |
| PHP / Composer | PHP 8.5.11, Composer 2.10.3; Image-Digests und Composer-PHAR-Prüfsumme gepinnt |
| Laravel / Livewire / maryUI | Laravel 13.35.0, Livewire 4.4.7, maryUI 2.9.10; Jetstream 5.5.3, Sanctum 4.3.3 |
| Bilder / PDF / Suche | Intervention Image 4.3.4, Laravel PDF 2.14.0, Dompdf 3.1.6, Scout 11.9.0, Typesense PHP 6.0.0 und Server 30.2 |
| PHP-Testwerkzeuge | Pest 5.3.1, PHPUnit 13.4.1, Browserplugin 5.1.2, Rectorplugin 5.0.6, Rector 2.7.0, PHPStan 2.3.1 |
| Node / npm / Build | Node 26.11.1, npm 12.2.0, Vite 8.3.4, concurrently 10.0.6 |
| JavaScript-Testwerkzeuge | Playwright 1.64.0, jsdom 30.1.2, Vitest und V8-Coverage 5.0.3 |
| Serviceimages | MariaDB 13.0.2, Nginx 1.30.5 Alpine 3.24 Slim, Typesense 30.2 |
| GitHub Actions | Versionsgeprüfte vollständige Commit-SHAs; unter anderem setup-node 7.1.0, setup-php 2.40.0, Upload 7.0.2, Download 8.0.2; Grype 0.120.1 |

Die übrigen direkten Pakete waren bereits aktuell. Stabile Major-Releases wurden berücksichtigt; Vorabversionen bleiben ausgeschlossen. PHP 8.5 und Node 26 werden konsistent in Manifesten, Docker und CI verwendet.

Die Composer-Auflösung aktualisiert 27 Pakete und entfernt das aufgegebene Paket `symplify/rule-doc-generator-contracts`. Die bisherigen Composer-Regeln gegen Advisories, Malware und aufgegebene Pakete bleiben aktiv. Die frühere PHPUnit-Grenze von Pest 5.2 ist mit dem zusammengehörigen Teststack-Update aufgehoben.

Im npm-Anwendungsgraphen sind unter anderem `shell-quote`, `source-map-js` und `postcss-selector-parser` korrigiert. Der eng begrenzte Typography-Override auf Parser 7.1.6 ist durch den Vite-/Prose-Build geprüft. Die strenge Freigabe von Installationsskripten bleibt bestehen.

Zusätzlich enthält selbst npm 12.2.0 verwundbare **gebündelte** Pakete, die ein Audit des Anwendungs-Lockfiles nicht erfasst. `docker/patch-npm-security.sh` ersetzt deshalb ausschließlich `undici` durch 6.28.1 und `brace-expansion` durch 5.0.12 einschließlich dessen `balanced-match`-Abhängigkeit. Ein eigenes Lockfile unter `docker/npm-security/` prüft Versionen und Integrität. Docker und alle npm-installierenden Workflows wenden denselben Patch an. Dependabot überwacht diesen kleinen Graphen separat; kompatible Releasezweige sind erforderlich, bis npm selbst die Korrekturen übernimmt. Danach kann der Patch entfallen.

maryUI verlangt weiterhin `jfcherng/php-diff` 6.x und damit `php-sequence-matcher` 4.x. Höhere transitive Major-Versionen werden nicht gegen diese Eltern-Constraints erzwungen. Nginx bleibt auf dem vom Hersteller als Stable bezeichneten Zweig 1.30; Mainline ist eine andere Releasekategorie.

OS-Sicherheitsupdates werden in den effektiven Composer-/Node-/PHP-/Nginx-/Typesense-Buildstufen installiert. Der Service-Scan prüft die tatsächlich gepatchten Buildstufen. Rohe Upstream-Images und sämtliche Funde bleiben als zusätzliche Berichte sichtbar. Behebbare High-/Critical-Funde blockieren weiterhin; Typesense blockiert sämtliche High-/Critical-Funde. Ein erfolgreicher Scan bedeutet deshalb bei den übrigen Images nicht automatisch, dass keine ungefixten Upstream-Funde existieren.

Der vollständige lokale Appimage-Scan mit der Grype-Datenbank vom 09.10.2026 meldet **203 High-/Critical-Paketfunde zu 52 unterschiedlichen CVEs**, davon 34 Critical- und 169 High-Paketfunde. Keiner besitzt laut dieser Datenbank einen verfügbaren Fix im gewählten Debian-Release. Mehrere Pakete desselben Quellpakets erscheinen separat; diese Zahlen sind keine 203 unabhängigen Anwendungslücken. Die Befunde bleiben im vollständigen Scan sichtbar und werden nicht durch Ignore-Regeln entfernt. Debian unterscheidet zum Beispiel eine noch ausstehende [MariaDB-Punktkorrektur](https://security-tracker.debian.org/tracker/CVE-2026-49261), ein an Digest-Authentifizierung gebundenes [curl-Problem](https://security-tracker.debian.org/tracker/CVE-2026-11856) und einen noch ungefixten [libxml2-Befund](https://security-tracker.debian.org/tracker/CVE-2026-86142). Ihre Auswirkungen sind paket- und nutzungsabhängig; ein sauberer Composer-/npm-Audit beseitigt diese OS-Befunde nicht.

Auch das offizielle alternative Basisimage `php:8.5.11-fpm-alpine3.24` wurde vergleichend gescannt: 13 High-Paketfunde zu fünf CVEs, darunter zwölf OpenSSL-Funde mit unbekanntem Fixstatus und ein behebbarer zlib-Fund. Es ist damit kein nachgewiesener Weg zu einem PHP-Stack ohne High-/Critical-Funde. Die getestete Debian-Runtime bleibt bestehen; ein Wechsel der C-Laufzeit von glibc zu musl benötigt eine eigene vollständige Kompatibilitätsprüfung. Wöchentliche Scans und frische Builds prüfen neu verfügbare Herstellerkorrekturen weiter.

## Tatsächlich genutzte Features und geprüfte Entscheidungen

| Plan | Umsetzung und Nutzen |
| --- | --- |
| F01 maryUI | Neue Input-Popovers erklären Straße und Mitgliedsbeitrag. Der Popover unterstützt Tastatur, Fokus, Escape und Touch, eindeutige IDs und ARIA-Verknüpfungen. Input, Password, Select, Textarea und File erhalten aus ihrer sichtbaren Beschriftung einen zugänglichen Namen, sofern kein eigener Name angegeben ist. Die Belohnungsverwaltung nutzt die aktuelle Tabs-Konfiguration mit aktivem Tab und Inhaltsklassen. |
| F02 Livewire | Der Dashboard-Feed nutzt `defer`: Die erste Dashboard-Antwort enthält einen zugänglichen Skeleton-Platzhalter und führt keine Feed-Abfragen aus. Filter, Nachladen und Beobachter werden nach dem Laden weiter initialisiert. Gezielte `wire:model.live.blur`-Bindungen erhalten die sofortige Validierung in bestehenden Formularen. |
| F03 Laravel | `app:verify-schedule` bewertet strukturiertes `schedule:list --json`, die zehn erforderlichen Aufgaben, Zeitzone und Ausführungsschutz. Das Deployment prüft den Scheduler unabhängig von textueller Tabellenformatierung. Queue-Pause/-Resume mit `--all` wird nach Verfügbarkeit erkannt; Worker werden vor Datenbackups geordnet beendet. |
| F04 PDF | Dompdf erhält lokale `font_dir`-/`font_cache`-Verzeichnisse unter `storage/app/private/pdf-fonts`. Deutsche Zeichen, kalter und warmer Cache und bestehende PDF-Bögen bleiben durch echte Exporte geprüft; Remotezugriff und Sandbox werden beibehalten. |
| F05 Image | Cover werden einmal dekodiert; unabhängige Klone erzeugen die Varianten. Rohbild-Grenzen werden vor EXIF-Orientierung kontrolliert, Zielmaße nach der Orientierung. Tests decken Alpha-PNG, EXIF-JPEG, Varianten und beschädigte/zu große Dateien ab. Temporäre Dateien werden atomar veröffentlicht und bei Fehlern aufgeräumt. |
| F06 Scout / Typesense | `kompendium:clone-index N` nutzt die [Collection-Klon-API](https://typesense.org/docs/30.2/api/collections.html) mit `copy_documents=true`, prüft Schema und Dokumentzahlen und sichert den Klon durch einen internen Snapshot. Der aktive Index bleibt unverändert. Lexikalische Versionen ab 2 erhalten eigene Collection-Namen. `kompendium:rebuild-index --resume` importiert nach einem Abbruch erneut, ohne den bestehenden Index zu löschen. Fehlende Dateien führen zu einem Fehlerstatus. |
| F07 Sitemap | Am 10.10.2026 liefert die öffentliche Sitemap einen Eintrag und 559 Bytes; der gemessene Abruf dauerte 167 ms. Das ist eine Abrufmessung, keine Generierungsbenchmark. Eine Aufteilung würde zusätzliche Dateien ohne Nutzen erzeugen. Die kompakte Struktur bleibt erhalten; bestehende Sitemaptests prüfen die Inhalte. |
| F08 Pest / PHPUnit | Neue Suite-Zeitlimits, Mutationserkennung, gecachte TIA-Baselines und verbesserte Browserdiagnosen werden genutzt. PHPUnit 13.4 erfasst auch bisher unterdrückte Pluginwarnungen: Der Browserjob legt die vom Plugin bereinigten Screenshot- und Traceverzeichnisse vorab an. Die Warnungsregeln bleiben aktiv. Die vollständige Suite bleibt Pflicht; TIA ersetzt sie nicht. |
| F09 Analyse | PHPStan Level 10 und Rector PHP 8.5 prüfen zusätzlich URI-, HMAC-, Scheduler- und Sync-Intervall-Helfer. Die Security-Helfer nutzen native URI-Validierung und streng geprüfte Secrets; redundante, nicht beobachtbare Zweige sind entfernt. Alle 130 erzeugten Mutationen werden erkannt, ohne Ignorierregeln oder verringerte Schwelle. |
| F10 UI / Lebenszyklus | DaisyUI-Zustände verwenden ARIA-basierte Varianten. Kontextinformationen und Fokus sind im echten Browser geprüft. Alpine-, Karten-, Chart- und Navigationstests bleiben erhalten. Der Three.js-Viewer verfügt bereits über explizites Cleanup von Geometrien, Materialien, Texturen, Observern, Frames, Renderer und Controls; ein neuer Object3D-Helfer ersetzt diese Aufgaben nicht. Ein umfassender WebGL-Dauertest ist damit nicht behauptet. |
| F11 Playwright | Die neue [Locator-API `within()`](https://playwright.dev/docs/api/class-locator#locator-within) begrenzt Statusprüfungen auf die richtige Fanfiction-Zeile und nutzt zugängliche Zellnamen. Ein manueller Shuffle-Lauf erhält einen protokollierten Seed. Erweiterte Retry-Traces und Fehlerartefakte werden 14 Tage gespeichert. Mobile RPG-Prüfungen verwenden lange Namen bei 320/390 Pixeln; Größenbegrenzungen an Feld und Select erhalten Umbruch statt globalem Abschneiden. |
| F12 Vitest / jsdom | [Vitest-Testprojekte](https://vitest.dev/guide/projects) trennen fünf reine Node-Testdateien von 26 DOM-Testdateien. Damit benötigen die Node-Helfer keine jsdom-Instanzen. Beide Projekte behalten ihre gemeinsamen Einstellungen und Coverage. Die CI führt die bestehende Ressourcenleckprüfung aus; Fokus-/Temporal-/Navigationsfälle bleiben erhalten. Aus Laufzeiten bei gleichzeitig laufenden Dockerprüfungen wird kein belastbarer Geschwindigkeitsgewinn abgeleitet. |
| F13 Build / Dev-Werkzeuge | Vite-Produktionsbuild, Assetmanifest und erneute Navigation werden geprüft. Node-only-APIs bleiben auf Node-Tools beschränkt. Neue Paketpublikationsfunktionen von npm passen nicht zu dieser privaten Webanwendung. Jetstreams Inertia-spezifische Änderung verlangt keinen Umbau der vorhandenen Livewire-Anwendung. |
| F14 MariaDB / Betrieb | MariaDB-Paralleltests prüfen atomare Buchungen und konkurrierende Änderungen. Zusätzliche SQL-Spezialpfade würden die SQLite-Kompatibilität ohne belegten Nutzen erschweren und werden nicht eingeführt. Docker Engine/Compose/Traefik auf dem echten Server benötigen eine eigene Betriebsinventur und Wartung; die lokalen fremden Stacks werden nicht aktualisiert. |

Die Test-Komponentenregistry wird nur in echten Konsolen-Unit-Tests verwendet. HTTP-Browsertests unter `APP_ENV=testing` rendern die echten maryUI-Komponenten. Dadurch werden Beschriftungs- und Fehlermeldungsprobleme nicht mehr durch Testattrappen verdeckt. Der Stapel-Angebotsfehler erscheint einmal als zugeordnetes `role="alert"`.

Bei Browseraktionen, die unmittelbar nach dem Login eine volle Seitennavigation auslösen, wird zuerst das tatsächliche Laden des verzögerten Feeds abgewartet. Das vermeidet künstlich abgebrochene Livewire-Anfragen in Firefox/WebKit. Die Fehlerprüfung bleibt bestehen. Die automatische Abbruchbehandlung von Livewire selbst wird nicht durch einen allgemeinen Fehlerfilter ersetzt.

Größere neue Produktfunktionen, insbesondere eine neue Suche mit Vektormodellen, werden nicht automatisch aktiviert. Bereits vorhandene Hybrid-Suchkonfiguration und deren lexikalischer Kill-Switch bleiben erhalten.

## Kontrollierter Suchindex-Wechsel

1. Indexschreibende Worker und Suchzugriffe im Wartungsfenster anhalten. Vor dem Klonen ein Datenbackup und eine Bestandszählung erstellen.
2. Auf dem aktiven lexikalischen Index `php artisan kompendium:clone-index 2` ausführen. Ein existierendes Ziel, ein fehlender Quellindex oder eine abweichende Dokumentzahl führt zum Abbruch. Das Kommando aktiviert und löscht keine Collection.
3. Unter isolierter Staging-Konfiguration Treffer, Phrasen, Filter, Rechte und Dokumentzahlen vergleichen. Der integrierte Typesense-Test prüft zwei echte Dokumente einschließlich Umlauten und identischer Treffer für Titel/Namen.
4. Erst danach `KOMPENDIUM_SEARCH_INDEX_VERSION=2` setzen, Konfiguration neu laden und Worker neu starten. Den Quellindex aufbewahren. Der Suchmodus und die Indexvariante werden nicht automatisch geändert.
5. Vor Wiederaufnahme der Schreibvorgänge kann durch Zurücksetzen der Version zurückgeschaltet werden. Nach neuen Schreibvorgängen muss der alte Index zunächst synchronisiert oder aus den privaten Roman-Dateien neu aufgebaut werden; ein alter Snapshot ist kein aktuelles Fallback.

Ein Klon behält das Schema bei. Ein Wechsel zu einem anderen Schema oder Embedding-Modell benötigt einen separaten Aufbau und eine Qualitäts-/Kapazitätsprüfung. Queue-Importe der nicht persistierten `RomanExcerpt`-Modelle werden nicht blind aktiviert: Die bestehende Import-Job-Queue bleibt zuständig, ihre Batches werden synchron per Scout übertragen.

Die echte Typesense-30.2-Prüfung zeigte, dass ein unmittelbar erfolgreicher Klon ohne Snapshot nach einem Neustart verschwinden kann. Das Kommando fordert deshalb vor seinem Erfolg einen [internen Snapshot ohne Exportpfad](https://typesense.org/docs/30.2/api/cluster-operations.html#create-snapshot-for-backups) an. Die API-Berechtigung muss `operations/snapshot` erlauben; ausreichend Wartungszeit und freier Speicher sind erforderlich. Bei einem Fehler bleibt die aktive Version erhalten und ein eventuell vorhandener Klon wird zur Prüfung aufbewahrt. Original und zwei Klone haben danach einen Neustart sowie eine Offline-Sicherung und Wiederherstellung in einem neuen Volume bestanden: Schema, vollständige Dokumentinhalte und Umlautsuche stimmen überein.

## Deployment und Rückweg

`deploy.yml` baut und scannt App-, Typesense- und Nginx-Images und reicht unveränderliche GHCR-Digests weiter. Vor SSH müssen die sechs erforderlichen Releaseworkflows für exakt dieselbe aktuelle `main`-Revision erfolgreich sein; ein vorhandener CodeQL-Lauf wird ebenfalls berücksichtigt. Fehlgeschlagene, übersprungene oder noch laufende Pflichtchecks erlauben keinen Rollout. Eine überholte Revision wird nicht mehr ausgerollt.

`docker/prepare-deployment.sh` liest Projektname und Compose-Dateien aus dem laufenden `maddrax-app`-Container. Nur vorhandene Dateien innerhalb `/opt/stacks/omxfc` werden übernommen. Ein zusätzliches geprüftes Compose-Overlay setzt alle freigegebenen Digests; dadurch entfällt die bisher fragile Voraussetzung, den Typesense-Platzhalter zuerst von Hand in einer separaten Serverdatei einzubauen. Das Overlay und nicht geheime Deploymentmetadaten bleiben für manuelle Compose-Aufrufe erhalten.

Der Datenbank-Preflight erlaubt hier ausschließlich MariaDB 13.0.0–13.0.2 und führt das Patchupdate auf 13.0.2 durch. Eine ältere Hauptversion, eine höhere Version oder eine unbekannte Antwort stoppt den Rollout vor dem Anhalten von Diensten. Ein Major-Upgrade benötigt eine eigene getestete Migration und Wiederherstellung. Eine lokale Prüfung ersetzt keine Inventur des produktiven Servers.

Nach Wartungsmodus und geordnetem Worker-/Schedulerstopp entstehen unter `.deployment/backups/<UTC-Zeit>/`:

- die alte Umgebung und vollständig aufgelöste Compose-Konfiguration,
- `images.yml` mit den tatsächlich zuvor laufenden Image-IDs als lokale Rollback-Tags und dem alten Codevolume,
- ein konsistenter Datenbankdump und ein Offline-Archiv des Typesense-Datenmounts.

Diese Dateien enthalten teilweise Secrets und sind mit privaten Rechten abgelegt. Sie gehören weder ins Repository noch in CI-Artefakte. Sie werden nicht im Log ausgegeben. Jeder App-Digest erhält ein eigenes Codevolume; das alte bleibt erhalten. Das Deployment stoppt ausschließlich die sechs eigenen Anwendungsdienste und entfernt weder fremde Container noch vorhandene Datenvolumes. Nach erfolgreichem Healthcheck betrifft die Imagebereinigung nur alte, unreferenzierte Images mit dem Source-Label dieses Repositories.

Vor einem neuen Codevolume verlangt der Helfer einen unabhängigen, schreibbaren Mount für `/var/www/html/storage`. Liegen Uploads, private Romane oder Sessions noch im Codevolume, stoppt die Vorbereitung: Diese Daten müssen zuerst mit angehaltenen Schreibern in einen eigenen persistenten Mount kopiert, auf Vollständigkeit/Berechtigungen geprüft und bei App, Queue und Scheduler konsistent eingebunden werden. Das alte Volume bleibt erhalten. Lokale `storage/app`-Inhalte und Testdateien sind vom Docker-Buildkontext ausgeschlossen.

### App-Rollback ohne Datenbank-Downgrade

Die folgenden Befehle sind ein Wartungsablauf für den Server, keine bereits ausgeführte Produktionseinstellung. Das gewählte Backup muss zu diesem Release gehören und unter `/opt/stacks/omxfc/.deployment/backups/` liegen. Prüfen, dass zwischen den Versionen keine inkompatible Schema- oder Datenänderung erfolgt ist. Dieses Update führt selbst keine neue Datenbankmigration ein.

```bash
cd /opt/stacks/omxfc
umask 077
rollback_dir="$PWD/.deployment/backups/<geprüfter-UTC-Zeitstempel>"
project_name="$(docker inspect maddrax-app --format '{{ index .Config.Labels "com.docker.compose.project" }}')"
test -s "$rollback_dir/compose.yml" && test -s "$rollback_dir/images.yml"

rollback_compose() {
  docker compose --project-name "$project_name" \
    -f "$rollback_dir/compose.yml" -f "$rollback_dir/images.yml" "$@"
}

# Der aktuelle App-Container wird zuerst in Wartung genommen.
docker exec maddrax-app php artisan down
rollback_compose stop --timeout 360 queue
rollback_compose stop scheduler app nginx
cp "$rollback_dir/environment" .env.production

# Laufende Datenbank und Typesense bleiben auf ihren aktuellen Versionen.
rollback_compose up -d --no-deps app nginx
rollback_compose exec -T app php artisan config:clear
rollback_compose exec -T app php artisan route:cache
rollback_compose exec -T app php artisan view:cache
rollback_compose exec -T app php artisan app:verify-schedule
rollback_compose exec -T app php artisan up
# Nur auf Releases verwenden, deren queue:resume --help die Option --all zeigt.
rollback_compose exec -T app php artisan queue:resume --all
rollback_compose up -d --no-deps queue scheduler
```

Danach HTTPS, Anmeldung, Livewire, Suche, Queue und Scheduler prüfen. Die verwendeten privaten Compose-Dateien und der Projektname müssen für nachfolgende manuelle Aufrufe ausdrücklich weitergegeben oder als `COMPOSE_FILE`/`COMPOSE_PROJECT_NAME` in `.env.production` dokumentiert werden. Bei einem Fehler hält der Workflow an; er verspricht keinen automatischen Daten-Rollback.

### Datenbank-/Typesense-Wiederherstellung

Bei inkompatiblen Datenänderungen genügt ein App-Rollback nicht. Daten zuerst in **neue, getrennte Volumes** mit den passenden gesicherten Images wiederherstellen; beschädigte oder bereits aktualisierte Originalvolumes behalten. Einen MariaDB-Major-Downgrade auf demselben Datenverzeichnis vermeiden. Den SQL-Dump importieren, Tabellen/Zeilen/Fremdschlüssel und Fachfunktionen prüfen; das Typesense-Offline-Archiv separat wiederherstellen und Collection-/Dokumentzahlen und Suchergebnisse vergleichen. Erst anschließend die betroffenen Volume-Zuordnungen im privaten Compose-Overlay umstellen. Nach Wiederfreigabe entstandene Daten brauchen eine bewusste Zusammenführung; sie dürfen nicht durch unbemerkte Backup-Rücksicherung verloren gehen.

Vor einem echten Server-Major-Upgrade sind der vollständige Produktionsdump, Wiederherstellungsdauer, Kapazität, Serverversionen und Proxykonfiguration in einer getrennten Umgebung zu prüfen. Docker Engine 29.9, Compose 5.6 und Traefik 3.7.14 sind recherchierte Wartungsziele; ihre Installation auf diesem Server ist nicht nachgewiesen und wurde hier nicht vorgenommen.

## Prüfung und Abnahmestand

Alle lokalen Anwendungsprüfungen laufen in getrennten Docker-Umgebungen mit synthetischen Schlüsseln und Daten. Echte `.env`-Dateien und persönliche Compose-Notizen werden nicht übernommen.

| Prüfung | Ergebnis / Stand |
| --- | --- |
| Composer-/npm-Lockfile-Audits | Keine gemeldeten Paket-Advisories; Composer einschließlich Dev-/Abandoned-Prüfung |
| Vitest / Coverage | 450 Tests in 31 Dateien erfolgreich; Zeilen 84,27 %, Funktionen 86,11 % |
| Vitest Ressourcenlecks | 450 Tests erfolgreich |
| Mutation | 100 %: 130 von 130 Mutationen erkannt; 39 funktionale Securitytests |
| PHPStan / Rector / Architektur / Profanity | Erfolgreich; Architektur sechs Tests |
| PHP-Feed-/Indexregressionen | 48 Tests, 163 Assertions erfolgreich |
| Pest Browser Preview | Ein Test, 77 Assertions; Exit-Code 0 mit unveränderten Warnungsregeln |
| MariaDB Maddraxikon | 286 Tests, 1.559 Assertions erfolgreich |
| MariaDB RPG-Konkurrenz | 15 Tests, 150 Assertions erfolgreich |
| MariaDB Veranstaltungs-Baxx | Acht Tests, 86 Assertions erfolgreich |
| MariaDB Backup / Restore | Echter konsistenter SQL-Dump, Neuimport und Vergleich von Umlauten, Join-Ergebnis und Fremdschlüsselprüfung erfolgreich |
| Echte Typesense-Integration / Restore | Schema-/Dokumentklon, identische Treffer, Rückschaltung, Neustart und Vergleich aller drei wiederhergestellten Collections erfolgreich |
| Typesense Snapshot-Regressionen | Sechs Tests, 42 Assertions erfolgreich; fehlgeschlagener Snapshot blockiert die Freigabe |
| maryUI / Feed in drei Browsern | Sechs gezielte Fälle erfolgreich |
| RPG / Mobil / PDF in drei Browsern | Sechs gezielte Abläufe erfolgreich; finale Mobilprüfung mit langen Namen und Popover-/Feed-Prüfung in WebKit erfolgreich |
| Charakter-Editor | Drei korrigierte Speicherabläufe in Firefox erfolgreich; Agarther, Marsianer und Morlock in WebKit erfolgreich, Morlock nach einem Timeout nochmals geprüft |
| Node Deploymentprüfungen | Acht Fälle erfolgreich; Digests, Projekte, unabhängiger Storage, Major-/Downgrade-Abwehr, Backups und Releasechecks |
| Actionlint / ShellCheck / Pint | Finale Workflow-/Shellprüfung und Stilprüfung der geänderten PHP-Dateien erfolgreich |
| Images | Development-, Playwright- und Produktionsbuild erfolgreich; Produktions-Smoke prüft Routes, Views, Scheduler, Manifest und PHP-Erweiterungen; Typesense besteht auch den vollständigen High-/Critical-Scan |
| Vollständige PHP-Suite / Coverage | 3.298 Tests, 13.943 Assertions erfolgreich; 29 übersprungen; Zeilen 85,72 %. Die nachfolgende Snapshot-Korrektur ist zusätzlich separat und mit dem echten Server geprüft. |
| Vollständiges Playwright Chromium / Firefox | Chromium: 226 erfolgreich, zwei übersprungen. Der finale Firefox-Lauf mit allen Timing-/Fokuskorrekturen läuft. |

GitHub hat für `ccfaf997482041851944ab510811c8fab52d6400` alle relevanten Prüfungen erfolgreich abgeschlossen, einschließlich [PHP-/Datenbank-/Coverage-Prüfungen](https://github.com/McNamara84/omxfc-vereinswebseite/actions/runs/38023318820) und [Playwright](https://github.com/McNamara84/omxfc-vereinswebseite/actions/runs/38023318914). Firefox benötigte dort sechs Wiederholungen; die anschließenden Feed-Synchronisierungen beheben deren beobachtete Ursache. Die nachfolgenden Korrekturen werden auf ihrer eigenen Revision erneut geprüft. Ein lokaler Erfolg macht einen früheren roten Lauf nicht nachträglich grün.

Die Umsetzung befindet sich auf `chore/dependencies-upgrade` beziehungsweise PR #757. Die jeweils aktuelle Revision und deren Checks müssen vor dem Rollout überprüft werden. Ein Merge, Produktionsdeployment, eine produktive Datenmigration und Hostupdates wurden hier nicht ausgeführt. Die noch erforderliche Serverinventur und gegebenenfalls Datenbank-/Storage-Migration sind bewusst vor dem Rollout durch Preflights abgesichert.

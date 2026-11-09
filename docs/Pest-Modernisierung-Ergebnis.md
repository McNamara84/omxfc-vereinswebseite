# Ergebnis der Pest-Modernisierung

Stand: 8. November 2026. Ausgangsrevision: `30a9addfbaf0ad300f298ebca7b0956ed1b699c6`.

## Umsetzung

Die 402 bisherigen PHPUnit-Klassentestdateien verwenden jetzt funktionale Pest-Tests.
Die Zuordnung aller 3.533 bisherigen Methoden ist in
[`tests/pest-migration-map.json`](../tests/pest-migration-map.json) festgehalten und
gegen Originalquellen sowie das neue Inventar geprüft: keine fehlenden Nachfolger.
195 lokale Fixture-Traits und fünf abstrakte Fixture-Basen erhalten typisierte
Eigenschaften, Hilfsmethoden und besondere Transaktions-Lifecycles. Die abstrakten
Basen enthalten keine Testfälle. 127 einmal verwendete Provider wurden zu lokalen
Dataset-Closures; gemeinsam verwendete Provider-Helfer bleiben gemeinsam.

Nickname-Varianten nutzen gebundene Datasets. Geldbeträge und RPG-Kosten haben
zusätzliche Grenz- und Fehlerfälle. Vier explizite Fanfiction-Fälle sichern verknüpfte,
externe, fehlende und leere Autorennamen deterministisch ab. Die reguläre Suite wächst von 4.739 auf
4.778 ausgeführte Fälle. Unverändert bleiben 74 begründete SQLite-Skips; die
Datenbankverträge sind zusätzlich unter MariaDB ohne Skips geprüft.

Strikte Vergleiche bleiben strikt. Rector-Regeln, die leere Literale, Array- oder
Objektidentität abschwächen oder mehrteilige Catch-Prüfungen verlieren können,
sind ausgeschlossen. Exception-Meldungen, Rollbacks und Dateicleanup bleiben
geprüft. Die notwendigen Anpassungen für `strict_types` betreffen explizite
String-Konvertierungen und bestehende strikte Containment-Prüfungen.

`Tests\Support\PhpunitMetadata` erhält die bisherigen PHPUnit-Attribute, für die
der installierte Pest-Stack keine gleichwertige flüssige API bietet. Zwei Tests
prüfen die tatsächlich generierten Attribute und den Umgang mit von TIA
ausgeschlossenen Dateien. Metadaten erzeugen für solche Dateien keine neue
Testklasse und lösen keinen Fehler aus. Fünf weitere Tests zählen echte
Unterprozess-Ausführungen der Stabilitätsdiagnosen. `repeat` führt zweimal aus
und erhält frühere Fehler; `retry` wiederholt nur fehlgeschlagene JUnit-Fälle.
Der normale Testlauf benötigt weder Retry noch TIA.

Der PDF-Layout-Test sammelt Dompdf-Referenzzyklen nach jedem Fall ein. Das hält
die Coverage-Ausführung innerhalb des regulären PHP-Speicherlimits. Die drei
geänderten Anwendungsdateien enthalten ausschließlich PHPDoc beziehungsweise
begründete Mutationskommentare; der ausführbare PHP-Tokenstrom ist identisch
mit dem Ausgangsstand.

## Paketstand und Qualitätsprüfungen

| Werkzeug | Vorher | Danach |
| --- | --- | --- |
| Pest | 5.3.4 | 5.3.5 |
| Pest-PHPStan | 5.3.0 | 5.3.1 |
| PHPUnit | 13.4.5 | 13.4.5 |
| Reguläres PHP | 8.5.12 | 8.5.12 |
| PHP-8.6-Probe | vorhandener RC-Workflow | tatsächliches 8.6.0RC5 |

Die offiziellen Releases bestätigen [Pest 5.3.5](https://github.com/pestphp/pest/releases/tag/v5.3.5)
mit TIA-Korrekturen und [Pest-PHPStan 5.3.1](https://github.com/pestphp/pest-plugin-phpstan/releases/tag/v5.3.1)
mit einer Korrektur der Typableitung für `test()` ohne Klassenbindung.

Composer hat nur die beiden genannten Pakete aktualisiert. Die 201 festgeschriebenen
Pakete wurden unter beiden PHP-Laufzeiten installiert; Manifestvalidierung,
Plattformprüfung und Audit waren erfolgreich. Der PHP-8.6-Integritätscheck
bestätigt unverändertes Manifest, Lockfile und Paketmetadaten.

PHPStan Level 10 prüft jetzt 18 Unit-Testdateien statt zwei sowie 13 ausgewählte
Anwendungsdateien. Es gibt keine neue Fehlerbaseline und keine pauschalen Ignorierregeln.
Eine Probe über sämtliche Unit-Dateien zeigte zahlreiche bestehende dynamische
Typverträge; deshalb wurde die verpflichtende Erweiterung auf die sauber typisierten
Fachbereiche begrenzt. Eine vollständige statische Analyse aller Unit-, Laravel-
und Livewire-Tests wird hier nicht behauptet.

Rector prüft alle migrierten Unit-, Feature- und Integrationstests. PHPStan,
Rector-Dry-Run, alle acht Architekturtests, Profanity-Scan und Pint bestehen.
Die Architekturregeln schützen zusätzlich die Trennung von Anwendung und
Testbasisklassen sowie die Unabhängigkeit reiner Geld-/Kostenregeln von Controllern.

| Mutation-Bereich | Getestete Mutationen | Score |
| --- | ---: | ---: |
| Bestehende URI-/HMAC-Regeln | 130 | 100 % |
| Euro | 39 | 100 % |
| RPG-Kosten | 28 | 100 % |

Das neue `composer test:mutate:core` ist auch für Pull Requests verpflichtend.
Die breitere bestehende Prüfung bleibt auf `main`, geplant und manuell erhalten.
Die lokalen Prüflaufzeiten betrugen etwa 15 s, 10 s und 53 s für die drei Bereiche.
Bei Euro sind sechs äquivalente Mutationen einzeln begründet ausgenommen; die
100-%-Grenzen wurden nicht gesenkt. Details stehen in der Klasse und in
[`tests/README.md`](../tests/README.md).

## TIA und gefilterte Ausführung

TIA umfasst jetzt Unit, Feature und reguläre Integration. Der Baseline-Workflow
zeichnet mit PCOV auf; das ursprüngliche Coverage-Profil verwendet weiterhin
Xdebug. Der Xdebug-Fallback lädt einen frühen Filter für die anschließend von
Pest ohnehin entfernten Vendor-/Node-Dateien. Das CI-Zeitlimit bleibt bei 45 Minuten.

Ungültige UTF-8-Eingaben behalten ihre Werte, erhalten aber benannte Dataset-Zeilen.
Damit sind auch deren Test-IDs im JSON-Graph speicherbar. Der Workflow validiert
den tatsächlich geschriebenen Graph vor dem Upload, da Pest einen Schreibfehler
nicht zuverlässig als fehlgeschlagenen Prozess meldet.

Der endgültige frische Aufbau bestand mit 4.704 Fällen und 24.217 Assertions in
732,92 s unter PCOV 1.0.12. Der Graph ist gültiges JSON; der unveränderte Folgelauf
meldet korrekt keine betroffenen Tests.

| Temporäre Änderungsprobe | Ausgewählte Fälle |
| --- | ---: |
| `app/Support/Euro.php` | 81 |
| `resources/views/dashboard.blade.php` | 435 |
| `tests/Concerns/CreatesUserWithRole.php` | 1.382 |
| `config/app.php` | 4.206 |

Alle vier `--list-tests`-Proben beenden sich erfolgreich. Ihre Dateien werden
anschließend vollständig wiederhergestellt; die Kontrollauswahl meldet erneut
keine betroffenen Tests. Die Auswahl ergänzt die vollständigen Ausführungen und
ersetzt keine fachlichen Assertions.

Die PHP-Änderungsprobe wurde zusätzlich tatsächlich ausgeführt: alle 81
ausgewählten Fälle bestanden mit 285 Assertions in 6,61 s. Auch dabei bleiben
die übrigen Testdateien korrekt ausgeschlossen.

## Laufzeit- und Datenbankabnahme

| Prüfung | PHP 8.5.12 | PHP 8.6.0RC5 |
| --- | --- | --- |
| Reguläre Suite, Seed 91 | 4.704 bestanden / 74 Skips | 4.704 bestanden / 74 Skips |
| Maddraxikon-MariaDB | 562 bestanden, keine Skips | 562 bestanden, keine Skips |
| RPG-MariaDB | 47 bestanden, keine Skips | 47 bestanden, keine Skips |
| Veranstaltungs-MariaDB | 8 bestanden, keine Skips | 8 bestanden, keine Skips |
| PHPStan, Rector, Architektur, Profanity | erfolgreich | erfolgreich |

Die regulären Läufe dauerten lokal rund 394 s beziehungsweise 389 s. Die drei
MariaDB-Läufe benötigten rund 222/131/31 s unter 8.5 und 219/122/30 s unter 8.6.
Der manuelle Typesense-30.2-Test bestand zusätzlich mit fünf Assertions. Die
123 Node-Tests bestanden in Linux; die 13 unmittelbar betroffenen Inventar- und
Runner-Prüfungen wurden zusätzlich unter Windows erfolgreich ausgeführt.

Die Prüfungen liefen in getrennten Wegwerfcontainern mit isoliertem Dateisystem
und Testdatenbanken. Gemeinsame PHP-/Laravel-Deprecation-Logs und native
PHP-Fehlererfassung waren unter 8.6 aktiv. Der Quellcodeaudit meldete für 1.767
PHP-Dateien und 333 kompilierte Blade-Dateien jeweils null Probleme.

## Browser-Verträge

Die fünf Pest-Browserfälle bestanden mit 132 Assertions. Chromium und Firefox
bestanden jeweils mit 287 Fällen; jeweils zwei bestehende Ausnahmen bleiben
sichtbar: der zuvor deaktivierte Livewire-Uploadfall und der optionale
Modal-Screenshotexport. Der Screenshotexport wurde zusätzlich ausdrücklich in
Chromium ausgeführt und bestand. Es gab keine neuen Skips und keine Diagnose-Retries.

Alle vier Pest-Browserdateien verwenden dieselbe Laravel-/Rollen-Fixture-Basis.
Veraltetes `waitForText()` wurde durch automatisch wartendes `assertSee()` ersetzt.
Manuelle Screenshot-Aufrufe bleiben manuell; ihre bisherigen Assertions bestehen.

| Bereich | Pest | Eigenständiger Playwright-Vertrag |
| --- | --- | --- |
| Dark Mode | Header-Schalter, gespeicherter Zustand und Reload | Systempräferenz, Storage-Ereignisse und Kontrast |
| Dashboard | Responsive Metriken, Reihenfolge, automatisches Feed-Nachladen, Screenshot | Rollen, Aufgaben und weitere Dashboard-Zustände |
| Kompendium | Gekaufter Mitgliederzugang, Such-/Hilfselemente, Screenshot | Adminfilter, Status, Zugriffsrechte und Downloadlinks |
| Modals | Backdrop aller registrierten Vorschau-Modals | Eigenständige Vorschau-, Interaktions- und Screenshotprüfungen |

Es wurden keine Playwright-Verträge gelöscht. WebKit wurde nicht erneut ausgeführt,
da die Playwright-Flows und ihre Konfiguration unverändert sind. Die bestehende
Chromium-/Firefox-Abnahme bleibt maßgeblich.

## Coverage-Abnahme

| Messung | Abgedeckte Zeilen | Vollständig abgedeckte Methoden |
| --- | ---: | ---: |
| Bisherige versionierte Referenz | 25.806 / 30.018 | 1.994 / 2.926 |
| Frischer Ausgangsstand | 27.582 / 31.049 | 2.364 / 3.205 |
| Modernisierte Suite | 27.584 / 31.050 | 2.365 / 3.205 |

Der frische Vergleich verwendet unter beiden Ständen PHP 8.5.12, Xdebug 3.5.3,
den Quellenfilter `app/**/*.php` und Seed 23. Zwei vollständige Coverage-Shards
und die vier zusätzlich aufgenommenen Autorennamen-Fälle bilden eine disjunkte
Vereinigung sämtlicher 4.778 regulärer Fälle. Die Rohberichte wurden mit dem
vorhandenen, validierenden Coverage-Merger zusammengeführt.

Die zuletzt ergänzten Metadaten-Assertions betreffen eine bereits mit
`CoversNothing` markierte Infrastrukturprüfung. Sie verändern weder
Anwendungsquellen noch fachliche Testeingaben.

Beide Coverage-Gates bestehen insgesamt, je Datei und an den zuvor abgedeckten
Stellen unveränderter Dateien. Für die drei ausschließlich kommentierten Dateien
wurden die ausführbaren Token und die zuvor abgedeckten Stellen zusätzlich unter
Berücksichtigung verschobener Zeilennummern verglichen: keine Regression.
Auch die Fallzahlen wurden je bisheriger Testklasse verglichen: keine Abnahme;
Änderungen betreffen ausschließlich die fünf im Messwertebericht aufgeführten
Erweiterungen. Keine Referenz wurde abgesenkt oder überschrieben.

Die frische Vorher-Referenz bleibt unverändert als
`tests/coverage-baselines/php-pest-modernization-before.json` erhalten und wird
in CI zusätzlich zur bisherigen Referenz geprüft. Die PHP-Spitzenverbräuche der
beiden Coverage-Shards lagen bei rund 416 und 495 MiB. Die isolierten Messprofile
hatten vor und nach der Migration dasselbe Speicherbudget; das reguläre Limit
von 512 MiB wurde nicht angehoben.

Die lokalen Coverage-Laufzeiten betrugen vor der Umstellung rund 688/863 s und
danach 1.107/1.338 s je Shard. Mehrere Prüfcontainer teilten sich dabei denselben
Host. Diese Wandzeiten sind Diagnosewerte und keine Messung von GitHub-Runner-
Minuten oder ein kontrollierter Leistungsbenchmark. Die Fallzahl und die
abgedeckten Stellen sind die verbindlichen Vergleichskriterien.

## PHP 8.6 nach dem stabilen Release

Die lokale Docker-Abnahme prüft die acht Bereiche des vorhandenen Koordinators:
Laufzeit, Abhängigkeiten, Anwendung, PHP, MariaDB, Pest Browser, Chromium und Firefox.
Sie verwendet dessen Befehls- und Deprecation-Prüfung; der GitHub-Workflow wurde
lokal nicht als GitHub-Run ausgelöst. Der feste Statuscheck und die reguläre
PHP-8.5-Basis bleiben erhalten.

Für den späteren Wechsel müssen stabile PHP-8.6-Images und alle Erweiterungen
verfügbar sein. Danach sind derselbe Lockfile-Stand, vollständige Tests samt
Coverage, MariaDB, Browser und die Deprecation-Erkennung erneut zu prüfen.
Die produktive Laufzeit wird in einem eigenen Release-Schritt umgestellt.

## Nachweise

Die vollständigen lokalen JUnit-, Roh-Coverage- und Diagnoseartefakte liegen unter
`.tmp/pest-modernization/`. Die versionierte frische Vorher-Referenz hat SHA-256
`9b6435d0d15b9afa7949de026b124c3dff5430fcd2a6e64e29c2db8bc14b0941`.
Der Implementierungsplan und die zuvor vorgemerkten RPG-PDFs sind vom Commit
dieser Umsetzung ausgeschlossen.

Die [maschinenlesbaren Messwerte](Pest-Modernisierung-Messwerte.json) enthalten
Fallzahlen, Änderungen je bisheriger Testklasse und die SHA-256-Werte der
verwendeten Coverage-Artefakte.

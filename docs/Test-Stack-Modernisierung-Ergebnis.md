# Test-Stack-Modernisierung: Änderungen und Prüfergebnisse

Stand: 17. November 2026. Die kompatiblen Testwerkzeuge sind aktualisiert und die zugehörigen Tests modernisiert. JavaScript- und PHP-Coverage bleiben erhalten; zusätzliche Regressionstests erhöhen den Prüfumfang. Eine vollständige Freigabe als neuester und sicherster Test-Stack ist noch nicht möglich: Playwright 1.65.1 verursacht einen nativen Firefox-Absturz, das dadurch ältere Browser-Image überschreitet die Sicherheitsgrenze, und die vollständige WebKit-Abnahme bleibt offen.

Ausgangspunkt ist Commit `b3c173ce`. Die [maschinenlesbaren Messwerte](Test-Stack-Modernisierung-Messwerte.json) enthalten Paketstände, Fallzahlen, Coverage-Vergleiche, Sicherheitsbefunde und die offenen E2E-Fehler mit Artefakt-Prüfsummen. Die Implementierungsplanung und die bereits vorgemerkten RPG-PDFs gehören nicht zu diesem Änderungsumfang.

## Paketstand

| Werkzeug | Vorher | Nachher |
| --- | --- | --- |
| Vitest, V8-Coverage, Browser-Provider und UI | 5.1.0 | 5.1.0, weiterhin aktuell |
| jsdom | 30.1.3 | 31.0.1 |
| Vite | Lockfile 8.3.8, lokal 8.3.5 | 8.3.8, Installation synchronisiert |
| Pest | 5.3.5 | 5.3.7 |
| PHPUnit | 13.4.5 | 13.4.7 |
| ParaTest | 7.27.5 | 7.29.0 |
| PHPStan | 2.3.5 | 2.3.6 |
| Rector | 2.8.4 | 2.8.5 |
| Webmozart Assert | 2.5.0 | 2.5.1 |
| Playwright | 1.64.1 | 1.64.1; Upgrade auf 1.65.1 technisch blockiert |
| axe-core / Playwright-Integration | 4.14.0 | 4.14.0, weiterhin aktuell |
| Grype in den Image-Workflows | 0.121.3 | 0.122.0 |

Die übrigen installierten Pest-Plugins und direkten Testwerkzeuge wurden auf verfügbare Updates geprüft. Node 26.13.1 und npm 12.2.2 sind weiterhin die aktuellen Stände der verwendeten Runtime-Reihe beziehungsweise des Paketmanagers. Die PHP-Prüfungen verwenden PHP 8.5.12 und Xdebug 3.5.3; die zusätzliche TIA-Probe verwendet PCOV 1.0.12. Die Composer-Auflösung aktualisiert gezielt den Testverbund; produktive Framework-Pakete bleiben auf ihren bisherigen Versionen. Als einzige produktive transitive Abhängigkeit wurde dabei Webmozart Assert von 2.5.0 auf den kompatiblen Patch 2.5.1 mit aufgelöst. Dieses von PHPDocumentor verwendete Paket ist auch Bestandteil von Pests Kompatibilitätsprüfung; der Versionswechsel ist in den vollständigen PHP- und Coverage-Läufen enthalten. Der überholte ParaTest-7.28-Ausschluss entfällt nach erfolgreicher Prüfung mit 7.29.0. Das unabhängige Styling-Update auf daisyUI 5.8.1 ist nicht Teil dieser Testmodernisierung.

## Offener Playwright-Blocker

Der aktuelle stabile [Playwright-Release 1.65.1](https://github.com/microsoft/playwright/releases/tag/v1.65.1) wurde separat mit seinen vorgesehenen Browser-Binaries installiert. Unter Windows und Linux stürzt Firefox 159.0, Revision 1572, in beiden vorhandenen ARIA-/axe-Reihenfolgen nativ ab. Die Windows-Probe mit normalen Prozessrechten liefert `Page crashed` während der axe-Analyse beziehungsweise beim Rollen-Locator; unter Linux tritt derselbe Fehler auf. Chromium und WebKit bestehen die Windows-Probe.

Dies entspricht dem weiterhin relevanten Problem aus [Playwright #43967](https://github.com/microsoft/playwright/issues/43967) und [Mozilla #2090055](https://bugzilla.mozilla.org/show_bug.cgi?id=2090055). Ein geschlossener Playwright-Issue belegt hier keinen behobenen stabilen Firefox-Build. Deshalb bleibt der einheitliche Pin für `@playwright/test`, `playwright` und `playwright-core` auf 1.64.1. Firefox und alle bisher geprüften axe-Regeln bleiben aktiv. Die vollständige Umstellung auf den neuesten Playwright-Stand ist damit ausdrücklich noch nicht erreicht.

`npm run test:e2e:aria` verwendet die neue `playwright.aria.config.js` und prüft den vorhandenen Regressionstest ohne Laravel, Webserver oder Datenbank. Die CI führt diese Probe für den jeweiligen Browser vor der vollständigen Suite aus. Reguläre E2E-, Shuffle- und Benchmark-Befehle, der gemeinsame Test-Runner, der Docker-Playwright-Helfer einschließlich Build und Browserinstallation sowie die Playwright- und Benchmark-CI-Läufe verwenden jetzt den vorhandenen Deprecation-Check.

## Modernisierte Tests

- Feature-Lifecycle und Poll-Charts warten auf konkrete Mount-, Fehler-, Cleanup- und Datenzustände mit `vi.waitFor` und aussagekräftiger `message`. Negativfälle überschreiten weiterhin eine definierte Observer-/Task-Grenze; eine sofort erfolgreiche Negativassertion ersetzt keine Synchronisation.
- Chart-Mocks verwenden dateilokalen `vi.hoisted`-Zustand statt zusätzlicher Globals. Canvas-Spies und Global-Stubs werden nach jedem Test zurückgesetzt. Die Global-Exposition der Mitgliederkarte stellt auch bei Testfehlern das ursprüngliche Window wieder her.
- App-Bootstrap, Activity-Feed, Karten-Lifecycle und Profil-Tour stellen Globals beziehungsweise Prototyp-Deskriptoren wieder her. Profil-Tour-Einladung, regulärer Abschluss und ausgewählte Fehler-/Speicherfälle verwenden fachliche Wartebedingungen. Die Rendering-Barriere für bewusst ausstehende oder veraltete Requests bleibt erhalten.
- Ein zusätzlicher Lifecycle-Fall prüft die Freigabe eines weiterhin verbundenen DOM-Knotens, der seinen Feature-Selektor verliert.
- Zwei zusätzliche Chromium-Fälle prüfen das Schließen vor dem verzögerten Fokusnachlauf und eine verspätete Suchantwort nach Escape. Sie verwenden gezielte Fake Timer; das Browser-Setup räumt sie nach dem Zerstören der Komponenten auf und stellt echte Timer wieder her.
- Kassenbuch- und Umfrage-Specs verwenden ihre vorhandenen wartenden Assertions ohne pauschale zusätzliche Schlafzeiten. Die Mitglieder-Anmeldung wartet auf die Ziel-URL; die Fanfiction-Veröffentlichung auf den sichtbaren Veröffentlichungszustand.
- Mitglieder- und Rollenprüfungen warten nach der Anmeldung auf den fertig geladenen Dashboard-Feed. Die Rollenprüfung lädt jedes Menüziel einmal mit einem nativen Frame-Aufruf und einem Budget von 30 Sekunden. Damit erzeugt der bisherige Acht-Sekunden-Navigationshelfer bei einer inzwischen großen Mitgliederliste keine zusätzlichen konkurrierenden Requests. HTTP-200-, sichtbare Inhalts- und Fehlerseitenprüfungen sowie das gesamte Testbudget bleiben erhalten.
- Die drei PDF-Popup-Fälle verwenden einen einzelnen nativen Klick. Der vorhandene Locator-Helfer kann unter WebKit bereits beim Probe-Klick absenden; im gezielten Nachlauf wurden zwei POSTs statt eines POSTs nachgewiesen. Die Prüfung auf genau eine Übermittlung bleibt erhalten. Das Barbar-Fixture wartet nach seinen reaktiven Änderungen auf Alpines Renderzyklus und prüft weiterhin die tatsächlich serialisierten Fertigkeiten. Popup-Ereignisse verwenden das jeweilige Testbudget; nur die drei betroffenen WebKit-PDF-Fälle erhalten wegen der aufgezeichneten Browserlatenzen 60 Sekunden. Ein Trace zeigte das Popup nach 5,46 Sekunden und damit nach Ablauf des bisherigen Fünf-Sekunden-Sonderlimits.
- Der Feature-Benchmark benennt jeden der fünf Läufe pro Szenario und Modus mit `test.step`. Das macht einen abgebrochenen Messpunkt im Trace erkennbar; Messumfang, Ausgabeformat und Zeitbudget bleiben erhalten.
- Ein zusätzlicher Node-Vertrag sichert den Deprecation-Check für alle Vitest-/Playwright-Aufrufe des gemeinsamen Runners unter Host und Docker ab. Dependabot gruppiert den Pest-/PHPUnit-/ParaTest-/PHPStan-Verbund sowie die Playwright-Pakete zusammen.

Bestehende fachliche Testfälle wurden erhalten. Die Vitest-Suite wächst von 627 auf 628 Node-/DOM-Fälle und von drei auf fünf native Browserfälle. Bereits passende Temporal-, Debounce-, Polling-, Deferred-Promise-, Lifecycle- und Infrastrukturprüfungen bleiben bestehen. Experimentelle inkrementelle Auswahl und Trace-Timeline bleiben lokale Optionen; Import-Map-Mocks bleiben im regulären Browserpfad deaktiviert.

## TIA und echte Dateisynchronisation

Pest 5.3.7 erfasst beim TIA-Aufzeichnen zusätzlich Dateilesezugriffe über einen eigenen [`ReadCapture`-Stream-Wrapper](https://github.com/pestphp/pest/blob/v5.3.7/src/Plugins/Tia/ReadCapture.php). PHPs natives `fsync()` lehnt diesen Stream mit `Can't fsync this stream!` ab. Dies wurde unabhängig von der langen Suite direkt reproduziert: derselbe geöffnete Stream liefert über den Wrapper `false`, über dessen nativen Deskriptor `true`. Die ersten beiden vollständigen TIA-Proben scheiterten deshalb an Atomic-Writer-/Crawler-Fällen. Ein gezielter Lauf allein reproduzierte den Fehler zunächst nicht; erst die direkte Wrapper-Probe klärte die Ursache.

`tests/tia-bootstrap.php` lädt ausschließlich für diese Testkonfiguration eine kleine namespaced Kompatibilitätsfunktion. Sie reicht bei diesem konkreten Pest-Wrapper den nativen Deskriptor an PHPs echtes `fsync()` weiter. Reguläre Tests und Anwendung verwenden weiterhin die native Funktion direkt. Die Aufzeichnung von Dateilesezugriffen bleibt aktiv; Anwendungscode, Schreibprüfungen und Fehlerverhalten werden nicht abgeschwächt. Vendor-Dateien werden nicht verändert. Diese Hilfe ist an den derzeitigen Pest-Wrapper gebunden und soll nach einem entsprechenden Upstream-Fix entfernt werden.

Der neue isolierte Prozess-Regressionsfall prüft native und aufgezeichnete Dateien einschließlich ihrer Inhalte, das tatsächliche Scheitern bei einem nicht unterstützten Stream sowie den `TypeError` bei einem geschlossenen Handle. Er besteht unter PHP 8.5 und PHP 8.6.0RC7. Damit steigt die reguläre PHP-Fallzahl um einen Fall.

Die vollständige TIA-Aufzeichnung besteht anschließend mit **je 4.767 erfolgreichen Fällen und 24.581 Assertions** sowohl unter dem regulären Xdebug 3.5.3 als auch unter PCOV 1.0.12. Nur `phpunit.tia.xml` erhält 1 GiB Speicher, weil der vollständige Abhängigkeitsgraph mit Xdebug das bisherige 512-MiB-Limit überschritt. Das reguläre Test- und Coverage-Profil behält 512 MiB. Der neue Wrapper ist keine alternative Coverage-Implementierung; die vergleichbaren PHP-Coverage-Messungen verwenden weiterhin Xdebug.

Die anschließenden Auswahlproben in isolierten Git-Kopien bestätigen das Verhalten unter beiden Treibern: Ohne Änderung werden keine Tests ausgewählt. Eine semantisch gleichwertige Änderung am Atomic Writer wählt 26 betroffene Fälle aus; alle bestehen mit 169 Assertions. Nach Wiederherstellung bestehen dieselben 26 Fälle; ein weiterer unveränderter Aufruf wählt wieder keine Tests aus. Original und wiederhergestellte Datei haben denselben SHA-256-Wert. Der Anwendungscode im Arbeitsverzeichnis wurde nicht verändert.

Ein zusätzlicher Xdebug-Vorlauf verwendete versehentlich eine ältere Bootstrap-Kopie und reproduzierte dadurch die bereits behobenen Synchronisationsfehler. Für den vollständig erfolgreichen Nachlauf wurden die Quellen neu kopiert und sechs kritische Bootstrap-, Test-, Konfigurations- und Lockfile-Prüfsummen mit dem Arbeitsverzeichnis abgeglichen. Diese Nachweise sind im JSON-Bericht enthalten.

## Geprüfte Funktionsgruppen

| Gruppe | Entscheidung |
| --- | --- |
| Async-Lifecycle, Observer und Chart-Updates | Zustände und Cleanup gezielt abwarten; negative Fälle über eine definierte Task-Grenze absichern. |
| Globale Mocks, Prototypen und Komponenten-Lifecycle | Betroffene Lecks durch restorable Stubs/Spies beseitigen; vorhandene sinnvolle Komponenten- und Netzwerk-Mocks behalten. |
| Temporal, Debounce, Polling, Deferred-Promises und Event-Helfer | Bestehende gezielte Tests passen bereits; kein pauschaler Umbau. |
| Native Dialoge, Fokus, Escape und verspätete Requests | Zwei zusätzliche echte Browserfälle; Timer nach jedem Fall vollständig zurücksetzen. |
| Playwright-Locators, Formulare und Navigation | Fachliche Zustände statt zusätzlicher Schlafzeiten; PDF-Einzelaktion und reaktive Formularbereitschaft nach gemessenen Fehlern korrigieren. Gleiche-URL-POSTs behalten die erforderliche Navigationssynchronisation. |
| Traces, Reporter und Performance | Bestehende Artefakte behalten; Benchmark-Läufe als benannte Schritte sichtbar machen. |
| PHP-Datasets, Assertions, Mutation und Architektur | Bestehende funktionale Struktur und Mindestwerte behalten; keine rein kosmetische Migration. |
| TIA | Neue Dateileserfassung behalten und native Dateisynchronisation im Test-Bootstrap kompatibel ausführen. |
| Coverage, Snapshots und Experimente | V8-Provider und Referenzen behalten; kein zusätzlicher Ausschluss, kein Providerwechsel und keine experimentelle CI-Auswahl. Snapshot-Interaktion und Pointer-Experiment haben im vorhandenen Umfang keinen belegten zusätzlichen Nutzen. |

## JavaScript-Coverage

Frische Messungen vor dem Upgrade und nach jsdom-Migration sowie Testmodernisierung verwenden denselben V8-Provider und dieselben Anwendungsquellen.

| Metrik | Vorher | Nachher |
| --- | --- | --- |
| Zeilen | 3.911 / 4.386 | 3.912 / 4.387 |
| Statements | 4.412 / 5.001 | 4.413 / 5.002 |
| Funktionen | 1.063 / 1.197 | 1.063 / 1.197 |
| Zweige | 2.667 / 3.452 | 2.667 / 3.452 |

Der Vergleich mit der frischen Ausgangsmessung und der versionierten Referenz besteht insgesamt, je Datei und an unveränderten Quellstellen. Die zusätzliche Konstante im nun ebenfalls geschützten Docker-Playwright-Helfer wird vollständig abgedeckt und erhöht Zeilen sowie Statements jeweils um eins; die Anwendungsabdeckung bleibt erhalten. Windows und Linux liefern dieselben Zähler. Keine Coverage-Referenz wurde abgesenkt. Der separate Browser-Pilot überschreibt die Node-/DOM-Coverage nicht.

## PHP-Coverage und Erhalt der Prüffälle

Vorher und nachher wurden je zwei vollständige Coverage-Shards mit derselben PHP-/Xdebug-Laufzeit, denselben Anwendungsquellen und derselben Quellenabgrenzung zusammengeführt. Der zusätzlich eingeführte TIA-Regressionsfall prüft ausschließlich Testinfrastruktur; er wird danach im vollständigen regulären Lauf separat nachgewiesen.

| Metrik | Vorher | Nachher |
| --- | --- | --- |
| Zeilen | 27.659 / 31.122 | 27.659 / 31.122 |
| Vollständig abgedeckte Methoden | 2.378 / 3.217 | 2.378 / 3.217 |

Alle 409 bisherigen Testklassen behalten ihre Fallzahlen. Beide Coverage-Messungen enthalten jeweils 4.850 Fälle: 4.766 erfolgreich und 84 bereits vorgesehene Skips; jeweils 24.578 Assertions. Der finale reguläre Lauf enthält zusätzlich den neuen TIA-Regressionsfall: **4.767 erfolgreich, 84 Skips, 24.581 Assertions in 410 Klassen**. Der Vergleich besteht gegenüber der frischen Ausgangsmessung und beiden geschützten PHP-Referenzen insgesamt, je Datei und an unveränderten Quellstellen. Keine Referenz, Quelle oder Mindestabdeckung wurde abgesenkt oder ausgeschlossen.

Die beiden Vorher-Shards beendeten sämtliche Fälle ohne fachliche Fehler, lieferten aber wegen der bisherigen Seed-Syntax beziehungsweise veralteter Shard-Metadaten Exitcode 1. Diese Warnungen sind in den Rohberichten erhalten; die Vorher-Läufe werden nicht als fehlerfrei beendete CLI-Aufrufe ausgegeben. Die endgültigen Nachher-Shards enden mit Exitcode 0. Ein Nachher-Coverage-Lauf überschritt zunächst das lokale 1.800-Sekunden-Limit und wurde mit einem lokalen Diagnosebudget von 3.600 Sekunden vollständig wiederholt; CI-Zeitbudgets wurden nicht erhöht. Die Wandzeiten unter gemeinsam genutzter Docker-Last sind keine kontrollierte Leistungsmessung.

## Bestätigte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| Windows: Vitest mit Coverage und Deprecation-Check | 628 / 628 bestanden; bisherige Coverage erhalten, eine zusätzliche Werkzeugzeile abgedeckt. |
| Linux: Vitest mit Coverage, Deprecation-Check und `--detectAsyncLeaks` | 628 / 628 bestanden; keine gemeldeten Lecks. |
| Windows: nativer Chromium-Pilot | 5 / 5 bestanden. |
| Windows: kompatibler Playwright-Pin mit isolierter ARIA-Probe | 6 / 6 über Chromium, Firefox und WebKit bestanden. |
| PHP 8.5: vollständige endgültige Chromium-/Firefox-Matrix | 586 erfolgreich, vier bestehende Skips, keine Fehler oder Retries; alle bisherigen Fallnamen erhalten. |
| Linux: endgültige vollständige Node-Suite einschließlich Bash-Installationsprüfungen | 124 / 124 bestanden, keine Skips. |
| Windows: betroffene Node-Verträge | 22 bestanden; drei erwartete Bash-/Linux-Skips. |
| PHPStan / Rector / Pint | Erfolgreich; die neuen PHP-Testhilfen bestehen auch die Formatprüfung. |
| PHP-Kompatibilitätsaudit | Je 1.779 PHP-Dateien unter PHP 8.5 und 8.6.0RC7 ohne Befund. |
| Pest-/ParaTest-Parallelbetrieb, PHP Unit | 706 Fälle mit 3.886 Assertions bestanden, zwei Prozesse. |
| Linux: Produktionsbuild und Feature-Bundle-Prüfung | Erfolgreich. |
| PHP 8.5: MariaDB-Hauptsuite / RPG / Veranstaltungs-Baxx | 566 / 57 / 8 Fälle bestanden; keine Skips. |
| PHP 8.5: RPG-Combat / Architektur | 268 / 8 Fälle bestanden. |
| PHP 8.5: alle vier Pest-Browserdateien | Fünf Fälle mit 132 Assertions bestanden. |
| Isolierter Typesense 30.2, interner Snapshot | Ein Fall mit fünf Assertions bestanden. |
| Mutation Testing | Bestehende 130 Mutationen erkannt, Score 100 %; zusätzliche Kernprüfungen ebenfalls 100 %. |
| Manuelle Modal-Screenshots | Zwei Chromium-Fälle ohne Skips bestanden; Screenshot-Export aktiviert. |
| Chromium-Benchmarks | Beide Benchmarks bestanden; alle fünf Feature-Messungen pro Szenario/Modus erhalten. Ein erster Lauf erreichte unter paralleler Last das bestehende 300-Sekunden-Budget; der erfolgreiche Nachlauf behält dieses Budget bei. |
| PHP 8.6.0RC7: vollständige reguläre PHP-Suite vor dem zusätzlichen TIA-Regressionsfall | 4.766 bestanden, 84 erwartete Skips; 24.585 Assertions. Der neue TIA-Regressionsfall ist zusätzlich erfolgreich geprüft. |
| PHP 8.6.0RC7: MariaDB, RPG-Combat, Architektur, PHPStan und Rector | Erfolgreich; alle drei MariaDB-Suiten ohne Skips. |
| PHP 8.6.0RC7: alle vier Pest-Browserdateien und neuer TIA-Regressionsfall | Sechs Fälle mit 135 Assertions bestanden. |
| TIA: vollständige Aufzeichnung und anschließende Auswahlprobe mit Xdebug und PCOV | Je 4.767 Fälle bestanden; unverändert keine Auswahl, nach gezielter Quelländerung jeweils 26 erfolgreiche Fälle. |

## Vollständige E2E-Läufe und Grenzen der Abnahme

Alle Browserläufe verwenden Playwright 1.64.1, einen Worker, keine Retries und isolierte Testdaten. Die ersten vollständigen PHP-8.5-Läufe umfassen je 295 Fälle:

| Browser | Erfolgreich | Vorhandene Skips | Fehler im vollständigen Lauf |
| --- | ---: | ---: | ---: |
| Chromium | 290 | 2 | 3 |
| Firefox | 289 | 2 | 4 |
| WebKit | 259 | 2 | 34 |

Die beiden Skips betreffen den ausdrücklich deaktivierten Roman-Upload und den optionalen manuellen Screenshot-Export. Der explizite Screenshot-Nachlauf besteht mit beiden Modal-Vorschau-Fällen ohne Skips. Es wurden keine neuen Skips oder erwarteten Fehler eingeführt.

Nach sämtlichen Korrekturen wurde die vollständige Chromium-/Firefox-Matrix mit PHP 8.5 erneut in jeweils zwei frischen Shards ausgeführt. Alle vier Aufrufe enden mit Exitcode 0, weiterhin ohne Retries:

| Browser | Shard 1 | Shard 2 | Endgültiges Ergebnis |
| --- | --- | --- | --- |
| Chromium | 149 erfolgreich, ein Skip | 144 erfolgreich, ein Skip | 293 erfolgreich, zwei bestehende Skips, keine Fehler |
| Firefox | 149 erfolgreich, ein Skip | 144 erfolgreich, ein Skip | 293 erfolgreich, zwei bestehende Skips, keine Fehler |

Damit bestehen **586 Browserfälle mit vier bestehenden Skips und ohne Fehler**. Ein zusätzlicher Vergleich der vollständigen Fallnamen und Browserzuordnungen bestätigt für beide Browser alle 295 bisherigen Fälle sowie exakt dieselben Skips. Die früheren fehlgeschlagenen Ergebnisse bleiben als Diagnosehistorie im Messwertebericht erhalten.

Zusätzlich wurden vor den letzten Synchronisationskorrekturen die vier vollständigen Chromium-/Firefox-Shards mit dem echten PHP-8.6.0RC7-Interpreter ausgeführt. Chromium erreicht dabei 290 erfolgreiche Fälle, zwei Skips und drei Fehler; Firefox 291 erfolgreiche Fälle, zwei Skips und zwei Fehler. Diese Fehler betreffen die anschließenden Mitglieder-/Menüprüfungen mit gewachsenen Fixtures. Nach der beschriebenen Synchronisationskorrektur bestehen alle drei Mitgliederfälle jeweils unter Chromium und Firefox. Eine zusätzliche PHP-8.6-Probe verwendet ausdrücklich die erhaltene Datenbank des zuvor fehlgeschlagenen vollständigen Shards: Alle vier Rollen-Menüschleifen und der Mitglieder-Kopierfall bestehen jeweils unter Chromium und Firefox. Damit ist die Korrektur auch gegen den zuvor langsamen großen Datenbestand geprüft. Die vollständige endgültige E2E-Abnahme oben verwendet die reguläre PHP-8.5-Basis; PHP 8.6 wurde nach diesen Korrekturen gezielt und nicht erneut vollständig im E2E-Umfang geprüft.

Die endgültigen PDF-/Formular-Nachläufe bestehen mit jeweils fünf Fällen in Chromium, Firefox und WebKit, insgesamt 15 Fällen. Die vorhandenen Prüfungen auf genau einen POST und tatsächlich serialisierte Formularwerte bleiben aktiv.

**Die vollständige WebKit-Suite ist weiterhin nicht abgenommen.** Die Probe gegen den erhaltenen großen Datenbestand scheitert dort unter anderem mit `Page crashed` und geschlossenen Browserkontexten. Ein ausschließlich temporärer Vergleich mit nativen Locator-Aktionen verbessert die 34 zuvor fehlgeschlagenen Fälle auf 24 erfolgreiche und zehn weiterhin fehlgeschlagene Fälle. Offen bleiben Fokus-/Tab-Fälle, RPG-Formular- und Modal-Zeitüberschreitungen, ein Konsolen-/CORS-Fall sowie Rollen-/Browserabstürze. Der globale Locator-Helfer wurde deshalb nicht pauschal ersetzt. Ohne frischen vollständigen WebKit-Vorherlauf wird nicht behauptet, dass sämtliche Fehler schon vor diesem Upgrade bestanden. Die bisherige CI-Matrix mit Chromium und Firefox bleibt unverändert; WebKit bleibt im lokalen vollständigen Testumfang enthalten.

## Sicherheitsprüfung und verbleibender Browserbefund

Die abschließenden `npm audit`- und `composer audit`-Prüfungen einschließlich Entwicklungsabhängigkeiten enden mit Exitcode 0: keine bekannten npm-Schwachstellen, keine Composer-Advisories und keine aufgegebenen Pakete.

Die PHP-, Node- und Browser-Testimages wurden aus den bestehenden Dockerfiles mit aktuellen Paketen neu gebaut. Die nativen Linux-Scans verwenden Grype 0.122.0 mit der unveränderten Richtlinie `--only-fixed --fail-on high`:

| Testimage | Befunde mit verfügbarer Korrektur | High/Critical | Richtlinie bestanden |
| --- | ---: | ---: | --- |
| PHP | 0 | 0 | Ja |
| Node | 2 | 0 | Ja |
| Browser | 251 | 165 | Nein |

Die beiden Medium-Befunde betreffen `glob` 13.0.6 und `minimatch` 10.2.5 im mitgelieferten npm 12.2.2. Die veröffentlichten Korrekturen erfordern laut den primären Advisories [glob 14.0.2](https://github.com/isaacs/node-glob/security/advisories/GHSA-3g3m-2fjr-6pw4) beziehungsweise [minimatch 11.0.1](https://github.com/isaacs/minimatch/security/advisories/GHSA-g3hv-75wg-m249). Auch die neueren Patches innerhalb der bisherigen Hauptversionen liegen weiterhin im betroffenen Bereich. Ein solcher erzwungener Hauptversionswechsel innerhalb des npm-Vendorbaums wurde nicht vorgenommen; der bestehende gezielte npm-Sicherheitspatch bleibt erhalten. Die erfolgreiche High/Critical-Richtlinie des Node-Images bedeutet deshalb nicht, dass dieses Image ohne bekannte Befunde ist.

Im Browser-Image kommen 249 Firefox-Zuordnungen hinzu. Die Zahl bezeichnet Scanner-Zuordnungen und keine unabhängig bestätigte Ausnutzbarkeit jedes einzelnen Treffers. Das mit Playwright 1.64.1 gekoppelte Firefox 157 enthält jedoch noch nicht die späteren Korrekturen aus den offiziellen [Firefox-159-Sicherheitshinweisen](https://www.mozilla.org/en-US/security/advisories/mfsa2026-118/) und [Firefox-160-Sicherheitshinweisen](https://www.mozilla.org/en-US/security/advisories/mfsa2026-125/). Der blockierte Playwright-Wechsel ist damit auch eine offene Sicherheitsgrenze. Das Browser-Image erhält keine uneingeschränkte Sicherheitsfreigabe.

Ein zusätzlicher Windows-Grype-Lauf ordnete Debian-Paketen generische NVD-/CPE-Treffer zu, obwohl die Paketversionen bereits Debian-Korrekturen enthalten. Die obige Bewertung verwendet die zur Linux-CI passende native Grype-Ausführung mit Distributionszuordnung. Es wurden keine projektspezifischen Ignore-Regeln hinzugefügt und keine Scanner-Grenzen gelockert.

Für die vollständige Abnahme als aktuellster Test-Stack werden ein stabiler Playwright-Release mit bestandener ARIA-/axe-Probe, ein bestandener Browser-Image-Scan und die Behebung beziehungsweise saubere Reproduktion der offenen WebKit-Fälle benötigt. Mit einem neuen Playwright-Stand sind die vollständigen E2E-Läufe erneut auszuführen.

Die aktuell verwendeten Releases sind anhand der primären Paketquellen geprüft:
[Vitest 5.1.0](https://github.com/vitest-dev/vitest/releases/tag/v5.1.0),
[jsdom 31.0.1](https://github.com/jsdom/jsdom/releases/tag/v31.0.1),
[jsdom-31-Migration](https://github.com/jsdom/jsdom/releases/tag/v31.0.0),
[Vite 8.3.8](https://github.com/vitejs/vite/releases/tag/v8.3.8),
[Pest 5.3.7](https://github.com/pestphp/pest/releases/tag/v5.3.7),
[PHPUnit](https://packagist.org/packages/phpunit/phpunit),
[ParaTest 7.29.0](https://github.com/paratestphp/paratest/releases/tag/v7.29.0),
[PHPStan 2.3.6](https://github.com/phpstan/phpstan/releases/tag/2.3.6),
[Rector 2.8.5](https://github.com/rectorphp/rector/releases/tag/2.8.5),
[axe-Integration 4.14.0](https://github.com/dequelabs/axe-core-npm/releases/tag/v4.14.0)
und [Grype 0.122.0](https://github.com/anchore/grype/releases/tag/v0.122.0).
Alle 14 externen GitHub-Action-Releases sind erneut geprüft und bereits aktuell; ihre Commit-Pins bleiben erhalten.

Die Prüfungen liefen lokal unter Windows und in isolierten Linux-Quellkopien. Windows stellt in dieser Sitzung kein PHP im `PATH` bereit; die PHP-/Composer-Abnahme verwendet die frisch installierten Linux-Abhängigkeiten und nicht den älteren Windows-Vendorbaum. Die GitHub-Workflows wurden geprüft, aber nicht als entfernte GitHub-Runs ausgelöst. PHP 8.6.0RC7 ist eine Kompatibilitätsprobe und keine Umstellung der produktiven PHP-Reihe. Ein erster PHP-8.6-Datenbankcontainer scheiterte am fehlenden nativen MariaDB-Client; nach dessen Installation bestehen alle drei vollständig wiederholten Suiten ohne Skips. Der Vitest-Browserstart meldet weiterhin einen dynamischen Import im generierten Modulrunner; der Produktionsbuild meldet große Chunks. Diese sichtbaren Hinweise sind keine Deprecations. Vendor- oder generierte Vite-Dateien wurden nicht von Hand angepasst.

Die isolierten Logs und Rohberichte liegen unter `.tmp/test-stack-modernisierung/`. Der versionierte JSON-Bericht speichert ihre relevanten Zähler und SHA-256-Werte; umfangreiche Roh-Coverage, Traces und temporäre Testumgebungen gehören nicht zum Commit.

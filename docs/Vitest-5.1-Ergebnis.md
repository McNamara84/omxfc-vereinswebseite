# Vitest 5.1: Modernisierung und Prüfergebnisse

Stand: 8. November 2026.

Vitest und der V8-Coverage-Provider wurden von 5.0.5 auf 5.1.0 aktualisiert. Die bestehenden Tests verwenden gezielte Zustandsprüfungen statt wiederholter Ereignisschleifen-Warteschritte, konsistentere Mock-Lebenszyklen und die neuen Zeit- und Diagnosefunktionen. Ein Chromium-Pilot ergänzt die Prüfung nativer Dialoge und Fokuswechsel. Die gemessene Abdeckung ist unverändert.

## Versionsstand

| Bestandteil | Verwendete Version |
| --- | --- |
| `vitest` | 5.1.0 |
| `@vitest/coverage-v8` | 5.1.0 |
| `@vitest/browser-playwright` | 5.1.0 |
| `@vitest/ui` | 5.1.0 |
| Playwright | 1.64.1, unverändert |
| Vite | 8.3.5, unverändert |
| jsdom | 30.1.3, unverändert |
| Node / npm | 26.13.0 / 12.2.1 |

Die Vitest-Pakete sind im Lockfile auf denselben Release-Stand festgelegt. `npm ci` war unter Windows und Linux erfolgreich; die Installation meldete keine Paket-Deprecations und das npm-Audit keine bekannten Schwachstellen.

## Änderungen an den Tests

- Die Temporal-Tests übergeben `Temporal.Instant` direkt an `vi.setSystemTime`. Ein zusätzlicher Fall prüft einen `Temporal.ZonedDateTime` an der wiederholten Berliner Herbststunde, das Fortschreiten des Instants und die unveränderte Prozesszeitzone.
- `fakeTimers.fakeNodeBuiltins` ist für Node/jsdom aktiviert. Der bewusst reale `setImmediate`-Helfer der Navigationssuche läuft weiterhin erst nach `vi.useRealTimers()`.
- Feature-Registrierung, Tournavigation und Cover-Sitzungen warten mit `vi.waitFor` auf konkrete Zustände. Die neue `message`-Option benennt den erwarteten Fortschritt bei Fehlern. Die expliziten Debounce-Grenzprüfungen bleiben erhalten.
- Neue `expect.poll`-Aufrufe meldeten in der Node-Leckprüfung offene Promise-Ressourcen. Diese Stellen verwenden deshalb `vi.waitFor` mit denselben fachlichen Assertions. Die abschließende vollständige Linux-Leckprüfung meldet keine Lecks.
- HTTP-Client und Hörbuch-Rollen-Repeater ersetzen Fetch und Alpine über rücksetzbare Globals. HTTP-Defaults und der vorherige Dokumentkopf werden nach dem Test wiederhergestellt.
- Die gemeinsame Konfiguration setzt `clearMocks: true` ausdrücklich für die Projekte `node` und `dom`. Damit werden auch die Aufrufhistorien der mit `vi.hoisted` erzeugten Mocks vor jedem Test und dessen `beforeEach` geleert; ihre Implementierungen bleiben erhalten. Vier redundante `vi.clearAllMocks()`-Aufrufe entfallen. Dieses Verhalten ist bereits [Standard seit Vitest 5](https://vitest.dev/guide/migration/#clearmocks-is-enabled-by-default); die explizite Einstellung macht den Isolationsvertrag sichtbar. Gezieltes Leeren innerhalb von Tests und fachlich erforderliches Cleanup bleiben bestehen.
- Der Storage-Abgleich zwischen Node und jsdom bleibt erhalten: Das Upgrade liefert keinen belegten Grund, diesen Kompatibilitätscode zu entfernen.

Kein bestehender Testfall wurde entfernt. Aus 626 Node-/jsdom-Fällen wurden durch den zusätzlichen Temporal-Fall 627. Der separate Browser-Pilot ergänzt drei Fälle.

## Browser-Pilot und Entwicklungsbefehle

`vitest.browser.config.js` verwendet echtes Chromium, das produktive Suchmodul und Alpine-Bindungen. Die Tests prüfen native Modalität, initialen Fokus, Suchergebnisse, Pfeiltastennavigation, Escape, Fokusrückgabe und einen leeren Zustand beim erneuten Öffnen. Die native Dialogimplementierung wird nicht gemockt.

Browser-Dateien sind aus dem jsdom-Projekt ausgeschlossen und haben eigenes Setup, Cache und Artefaktverzeichnis. `test:all` führt den Pilot auch im bestehenden Docker-Browserpfad aus; `test:fast` startet weiterhin keinen Browser. Der Docker-Quellabgleich kopiert die neuen Konfigurationen mit.

| Befehl | Verhalten |
| --- | --- |
| `test:watch` | Expliziter Watch-Modus. PHP-Abhängigkeiten und erzeugte Berichte sind von der Dateibeobachtung ausgenommen. |
| `test:failed` | Letzte Fehler erneut ausführen; ohne Fehler mit Status 0 enden. |
| `test:browser` | Den eigenständigen Chromium-Pilot ausführen. |
| `test:incremental` | Experimenteller lokaler Lauf mit `--since-last-run`. |
| `test:trace` | Experimentelle lokale Async-Trace-Timeline über eine separate Diagnosekonfiguration. |

Die reguläre Konfiguration und CI aktivieren die beiden neuen experimentellen Diagnose-/Auswahlfunktionen nicht. Im Browser sind die experimentellen Import-Map-Mocks ausdrücklich deaktiviert. Der stabile Trace View verwendet `failure-only`; der HTML-Browserbericht liegt unter `test-results/vitest-browser/`. V8 erzeugt zusätzlich zu den bisherigen JSON-Dateien einen HTML-Coverage-Bericht unter `coverage/`.

## Deprecation-Schutz

`tools/testing/check-deprecations.mjs` bietet zwei Einstiege: gespeicherte Logs prüfen oder einen Node-Prozess mit `--run` überwachen. Der überwachte Prozess erhält zusätzlich `--throw-deprecation` und `--trace-deprecation`; vorhandene Node-Optionen bleiben erhalten. stdout und stderr bleiben sichtbar, bereits fehlerhafte Exitcodes werden übernommen.

Die Erkennung berücksichtigt definierte npm-, Node-, Vitest-/Vite- und Browser-Warnformate sowie ANSI-Codes, geteilte Ausgabestücke und fehlende abschließende Zeilenumbrüche. Normale Warnungen und Testtitel mit dem Wort „deprecated“ lösen keinen Fehler aus. Es gibt keine allgemeine Unterdrückung von Warnungen.

Die CI verwendet diesen Schutz für Installation, Coverage-Lauf, Node-Prüfungen, Browser-Pilot und Leckprüfung. Derselbe Coverage-Lauf liefert weiterhin die Messdaten für den geschützten Vergleich. Die neuen Node-Tests prüfen positive und negative Fälle sowie echte Konfigurations- und Worker-Deprecations. Eine zusätzliche isolierte Browserprobe bestätigte: Ein erfolgreicher Browser-Test mit einer ausgegebenen Deprecation wird vom überwachenden Prozess dennoch mit Status 1 beendet.

In der Anwendungssuite traten beim Upgrade keine Deprecation-Warnungen auf, die durch Unterdrückung oder Ausnahmen hätten umgangen werden müssen.

## Coverage-Vergleich

Vor dem Upgrade wurde ein vollständiger Windows-Lauf mit Vitest 5.0.5 gesichert. Die endgültigen Windows- und Linux-Berichte mit 5.1.0 besitzen dieselben Zähler:

| Metrik | Abgedeckt / insgesamt, vorher und nachher | Anteil |
| --- | --- | --- |
| Zeilen | 3.911 / 4.386 | 89,17 % |
| Statements | 4.412 / 5.001 | 88,22 % |
| Funktionen | 1.063 / 1.197 | 88,80 % |
| Zweige | 2.667 / 3.452 | 77,25 % |

Sowohl die bestehende versionierte Referenz als auch der unmittelbar vor dem Upgrade gesicherte Bericht bestehen den Vergleich insgesamt, je Datei und an unveränderten Quellstellen. Die Coverage-Referenz wurde nicht geändert. Der Browser-Pilot überschreibt die Node-/jsdom-Messung nicht.

## Ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| Windows: vollständige Suite mit Coverage und Deprecation-Schutz | 627 / 627 bestanden; beide Coverage-Vergleiche bestanden. |
| Linux: endgültige Suite mit Coverage, Deprecation-Schutz und `--detectAsyncLeaks` | 627 / 627 bestanden; keine gemeldeten Lecks; beide Coverage-Vergleiche bestanden. |
| Chromium-Pilot unter Windows und Linux | Jeweils 3 / 3 bestanden; HTML-Bericht erzeugt. |
| Vollständige Node-Suite unter Linux | 119 / 119 bestanden, keine Skips. |
| Geänderte Node-Verträge unter Windows und Linux | Jeweils 20 / 20 bestanden. |
| Inkrementeller Lauf und Diagnosekonfiguration unter Windows und Linux | Erfolgreich; erster inkrementeller Lauf zeichnet alle 43 Dateien auf. |
| Watch-Smoketest unter Windows | Sechs Temporal-Fälle im tatsächlichen Watch-Modus bestanden; eigener Testprozess anschließend beendet. |
| Erneuter Lauf letzter Fehler ohne vorherige Fehler | Erfolgreicher Status 0. |
| Argumentweitergabe über `npm.cmd run … -- …` unter PowerShell | Dateifilter und `--reporter=dot` erfolgreich geprüft. |
| Produktionsbuild unter Windows und Linux | Erfolgreich, einschließlich Feature-Bundle-Prüfung. |
| Testinventar und Whitespace-Prüfung | Inventar aktualisiert; `git diff --check` erfolgreich. |

Die Linux-Prüfungen liefen in einer isolierten Quellkopie im vorhandenen lokalen Docker-Image `omxfc-full-modernize:local`, mit nativen Linux-Abhängigkeiten. Das Image enthält PHP 8.6.0RC5 für die vorhandenen PHP-Prozess-Fixtures der Node-Suite. Der GitHub-Workflow behält PHP 8.5 bei und wurde lokal angepasst, aber nicht auf GitHub gestartet. Der vollständige Deploymenthelfer-Lauf unter Windows scheitert an den vorhandenen Bash-/POSIX-Voraussetzungen; sein vollständiger Nachweis stammt deshalb aus Linux.

Der Browserstart meldet weiterhin einen Vite-Hinweis zu einem dynamischen Import im generierten Vitest-Modulrunner. Der Produktionsbuild meldet weiterhin große Chunks; der Windows-Build außerdem Plugin-Laufzeiten. Diese Meldungen sind keine Deprecations und werden sichtbar gelassen. Änderungen an Anwendungscode, PHP-Suiten, Datenbanken oder den bestehenden vollständigen Playwright-E2E-Abläufen waren für diese Testmodernisierung nicht erforderlich; diese vollständigen Anwendungssuiten wurden nicht erneut ausgeführt.

# Ergebnis: Baxx-Fachlogik und bedarfsabhängiges JavaScript

Stand: 24. Oktober 2026. Umfang: projektweite Baxx-Bausteine, gemeinsame
Challenge-Aktionen und bedarfsabhängige Karten-, Chart-, RPG- und 3D-Module.

## Struktur und Verträge

`app/Services/Baxx` bündelt sechs kleine Bausteine:

| Baustein | Verantwortung |
| --- | --- |
| `BaxxRuleData` | Unveränderliche Regeldaten; Intervallprüfung ohne UI-Formatierung. |
| `BaxxRuleResolver` | Basisregel und derzeit gültige Sonderaktion; kein langlebiger Cache für zeitabhängige Regeln. |
| `BaxxRulePresenter` | Bestehende Beschriftungen, UI-Arrays und Sonderaktionshinweise. |
| `BaxxProgressStore` | Fortschritt initialisieren, sperren und ausschließlich vorwärts bewegen. |
| `BaxxBookingWriter` | Eloquent-Buchung mit explizitem Benutzer, Team, Betrag, Quellenreferenzen und optionalem historischem Zeitpunkt. |
| `BaxxSpecialOfferManager` | Sonderaktionen speichern/aktivieren; konkurrierende Aktivierungen unter Regelsperren prüfen. |

Der Writer entscheidet weder über Berechtigungen noch über Einmaligkeit und
erwirbt keine versteckten Benutzer- oder Teamsperren. Seine Aufrufer verbinden
Quellenstatus und Buchung in einer Transaktion. Modellereignisse einschließlich
Meilensteinen bleiben erhalten. Ranglisten-Caches werden nach erfolgreichem
Commit auch bei Änderung und Löschung einer Buchung ungültig.

Die sieben Challenge-Aktionen sind in `app/Actions/Todos/TodoWorkflow.php`
zusammengefasst. So teilen sie ihre Sperr-, Autorisierungs- und Cachelogik,
während jede Aktion ihren Statusübergang ausdrücklich beschreibt. `TodoIndex`,
`TodoShow`, `TodoForm` und der weiterhin vorhandene `TodoController` delegieren
dorthin. `TodoRequest` verwendet dieselben Validierungsregeln. Alte Controller
und Views bleiben als kompatible Einstiege erhalten.

### Quelleninventar

| Quelle | Gemeinsame Bausteine und erhaltene Besonderheiten |
| --- | --- |
| Challenges | `TodoWorkflow` und Writer; `todo_id` bleibt eindeutig, Verifizierung ist ein gesperrter Statuswechsel. Löschen einer verifizierten Challenge entfernt deren Buchung über das Modell. |
| Rezensionen | `RecordReview`, Resolver, Presenter, Writer; teambezogener Zähler und jede n-te Rezension. Live-Einreichungen zum selben Buch werden abgewiesen; historische Importe behalten ihre Zeitstempel und dürfen mehrere historische Rezensionen enthalten. |
| Maddraxiversum | `MissionCompletionService`, Resolver, Presenter, Writer; ein positiver missionsspezifischer Reward hat weiterhin Vorrang. Wiederholte Abschlussprüfung zahlt nicht erneut. |
| Romantausch | Resolver, Presenter, Fortschritt, Writer; Angebote in Bündeln zählen einzeln, Gesuche und beide Tauschseiten behalten ihre bisherigen Zählweisen. Wallet bleibt das Mitglieder-Team. |
| Cover-Bewertungen | Basisregel, Fortschritt, Writer; Lebenszeitanzahl einschließlich gelöschter Bewertungen, keine erneute Vergütung nach Wiederherstellung. Deaktivierte Regeln verbrauchen Fortschritt ohne spätere Nachzahlung. |
| Veranstaltungen | Writer unter bestehenden Veranstaltungs-/Mitgliedschaftssperren; bestätigte Teilnahme, berechtigte Mitglieder zum Abschlusszeitpunkt, Auditverknüpfung und Abschlussdaten bleiben erhalten. |
| Maddraxikon | Writer für positive Buchungen und Gegenbuchungen; Quellenidentität, Sitzungen, Policy-Snapshots, Tageslimit, Mitgliedschaft und Audit bleiben im Fachmodul. |
| Fanfiction | `PublishFanfiction` und Writer; Status, Reward, Aktivität und Autorenvergütung gemeinsam. Livewire und Controller verwenden `fanfiction_publish`. Die bestehende Zuordnung zum aktuellen Team des Autors bleibt erhalten. |
| Allgemeine Gutschriften | `User::incrementTeamPoints()` delegiert an den Writer und behält seine bisherige Teamzuordnung. |
| Käufe, Erstattungen, RPG-Slots | Vorhandene Wallet-Berechnung und Kaufsperren bleiben erhalten. Erstattungen lesen den Kauf erneut unter Sperre. Mehrfach kaufbare RPG-Slots bleiben von einmaligen Freischaltungen getrennt. |

Neue produktive `UserPoint`-Buchungen entstehen damit über den Writer;
gezielte Löschungen bleiben beim jeweiligen Quellenablauf. Quellen mit
eigener fachlicher Bewertung teilen die Persistenz, ohne ihre Bewertung in eine
universelle Regel umzudeuten. Es gibt keine Migration und keine Neuberechnung
historischer Guthaben.

### Sperrreihenfolgen

| Pfad | Reihenfolge |
| --- | --- |
| Challenge | Challenge → zugehörige Buchung; gleiche Challenge serialisiert Verifizierung/Löschung. |
| Rezension | Benutzer → Rezension erstellen → teambezogener Zähler → Buchung. |
| Mission | Benutzer → offene Mission → Abschluss/Zähler → Buchung. |
| Cover | Bestehende Benutzersperre → Bewertung → Fortschritt → Buchung. |
| Romantausch | Bestehende Quelltransaktion → Fortschritt je Benutzer/Aktion → Buchung. |
| Veranstaltung | Veranstaltung → Mitglieder-Team → Benutzer aufsteigend → Mitgliedschaften aufsteigend → Vergabe/Audit. |
| Maddraxikon | Bestehende Synchronisations-/Quellsperren und `MembersTeamMembershipLock` bleiben unverändert; dessen Ordnung ist Team → Benutzer aufsteigend → Mitgliedschaften aufsteigend. |
| Fanfiction | Geschichte → Status/Reward/Aktivität/Buchung. |
| Wallet-Kauf | Benutzer → aktuelle Kauf-/Guthabenprüfung → Kaufzeile. |
| RPG-Slot | Benutzer → Kaufzeilen → Charakterzeilen → aktuelle Guthabenprüfung. |
| Erstattung | Benutzer → frische Kaufzeile. |
| Sonderaktion | Basisregeln nach `action_key` sortiert → Sonderaktion; bei Romantausch werden alle drei Aktionen gesperrt, damit das Verschieben einer Aktion keine gegenläufige Sperrreihenfolge erzeugt. |

Unter MariaDB muss die Konfliktprüfung für Sonderaktionen eine sperrende aktuelle
Leseoperation sein: Ein zuvor aufgebauter `REPEATABLE READ`-Snapshot könnte die
gerade von einem konkurrierenden Aufruf aktivierte Aktion sonst übersehen.

## Frontend-Lebenszyklus

`resources/js/features/index.js` enthält feste Selektoren und dynamische Imports.
`lifecycle.js` dedupliziert Imports, verwaltet pro DOM-Wurzel einen
`AbortController` und ruft deren Cleanup vor Navigation oder nach DOM-Entfernung
auf. Späte Import-/Mount-Ergebnisse werden verworfen beziehungsweise sofort
aufgeräumt. Ein fehlgeschlagener Import zeigt einen erneuten Versuch an; andere
DOM-Änderungen lösen keine Endlosschleife von Wiederholungen aus.

Karten importieren Leaflet und dessen Styles selbst. Diagramme lesen aktuelle
Daten an ihrer Wurzel; Statistikdaten liegen an den jeweiligen Canvas-Elementen.
Umfrage-Charts beobachten weiterhin Ergebnisänderungen im bestehenden DOM.
Gesperrte Karten und Statistikbereiche erzeugen keinen Bedarf für die schwere
Bibliothek. Der kleine Statistik-Navigator bleibt unabhängig von Chart.js.

`<x-lazy-feature>` schützt Alpine-Unterbäume mit `x-ignore`, bis ihre Factory auf
der tatsächlich aktiven Alpine-Instanz registriert ist. Wiederholtes Scannen
initialisiert vorhandene Formulare nicht erneut. RPG-Kämpfe geben ihr Cleanup
an denselben Lifecycle zurück. Der 3D-Viewer entsorgt auch Modelle, die erst nach
dem Verlassen der Seite geladen werden.

`npm run build` und der Docker-Asset-Build prüfen den statischen Importgraph und
die Sourcemaps. Damit wird auch ein schweres Modul erkannt, das in einen
unauffällig benannten gemeinsamen Chunk oder direkt in `app.js` gerät.

## Nachgewiesene Fehlerkorrekturen

| Vorher | Jetzt und Regressionstest |
| --- | --- |
| Challenge-Status, Vergabe und Aktivität konnten getrennt geschrieben werden; konkurrierende Einstiege verwendeten duplizierte Abläufe. | Gemeinsamer gesperrter Workflow; Rollback, doppelte Verifizierung, Übernahme und Verifizierung gegen Löschung in `BaxxWorkflowTest` / `BaxxMariaDbConcurrencyTest`. |
| Eine Adminrolle im aktuell gewählten anderen Team konnte bei Challenge-Berechtigungen maßgeblich sein. | Rolle im Mitglieder-Team; `test_other_team_admin_cannot_verify_members_team_challenges`. |
| Rezensionserstellung/Zähler und Missionsabschluss konnten parallel mehrfach vergüten oder bei Buchungsfehlern Teilzustände hinterlassen. | Atomare Quellenaktionen, Benutzer-/Quellsperren; Rollback- und echte Mehrprozess-Tests. |
| Fanfiction-Livewire vergütete fest 5 Baxx, der andere Einstieg verwendete die konfigurierte Regel. | Beide verwenden dieselbe Veröffentlichung; konfigurierte/deaktivierte Regel, Wiederholung und Rollback getestet. |
| Bereits geladene Erstattungsmodelle konnten den inzwischen erstatteten Kauf erneut verarbeiten. | Kauf unter Sperre frisch lesen; Regression in `RewardServiceTest`. |
| `reviews:import-old --fresh` entfernte auch nicht zuordenbare Baxx des Mitglieder-Teams. | Abbruch ohne Löschung bei vorhandenen Buchungen; ohne Buchungen nur Mitglieder-Rezensionen zurücksetzen. Tests in `ImportOldReviewsCommandTest`. |
| Regelobjekte konnten nach Änderung oder Sonderaktionsablauf im selben Service veraltet bleiben. | Frische Auflösung; Zeitgrenzen und Änderungen an derselben Instanz getestet. |
| Die Missionsschaltfläche erhielt von der Blade-Komponente keine nutzbare DOM-ID; der native Dialog wertete zusätzlich einen unpassenden Alpine-Fokustrap aus. | Explizite Schaltfläche mit ID, native Dialog-Fokusverwaltung; echte Karteninitialisierung in `feature-lifecycle.spec.js`. |
| Ressourcen konnten nach verspätetem Laden einer verlassenen Featureseite weiterlaufen. | Import-Abbruch, Cleanup von Karte/Chart/3D, Timer und Listener; Vitest und Browsernavigation einschließlich Fehler/Retry und Historie. |
| Der vollständige Teststarter führte alle Browserprojekte mit derselben, bereits veränderten SQLite-Fixture aus. Spätere Browser fanden unter anderem bereits übernommene Challenges und doppelte Buchungszeilen. | Ein eigener Playwright-Aufruf pro Browser baut Fixture und Server erneut auf; Regressionen für Docker-/Host-Aufrufe und die CI-Auswahl in `test-runner.test.mjs`. |

Die Leak-Prüfung deckte außerdem noch offene jsdom-`selectionchange`-Tasks in
einem vorhandenen Navigationstest auf. Dessen Teardown wartet jetzt auf diese
Tasks; das Produktionsverhalten der Suche wurde nicht geändert.

## Messungen

Ausgangscode ist der archivierte Git-Stand `4fdc1861b7f316ca60a16478edaff0d2229525dd`. Vorher und
nachher verwenden dieselben Abhängigkeiten und Testimages. Messungen laufen auf
dem lokalen Docker-Host neben anderen Diensten; Zeitwerte dienen deshalb der
Einordnung und bilden keine Produktions-SLA ab.

### Backend

Je fünf Durchläufe derselben sechs vorhandenen Tests mit PHP 8.5.12 und MariaDB
13.0.2, ohne Coverage. Vier CPU-Kerne sind dem Benchmarkcontainer zugeordnet.
Gezählt wird der vollständige Testmethoden-Workflow einschließlich seiner
Fixtures und Assertions, jedoch ohne `setUp`, Migrationen und Seeder. SQL-Werte
stammen aus dem Laravel-Querylog. Externe Maddraxikon-Antworten sind gemockt.

| Szenario | SQL vorher → nachher | Laufzeitmedian ms vorher → nachher | Spannweite ms vorher / nachher | `FOR UPDATE` vorher → nachher |
| --- | ---: | ---: | --- | ---: |
| Challenge verifizieren | 59 → 58 | 121,85 → 129,91 | 97,52–183,29 / 116,53–136,91 | 2 → 3 |
| Jede zehnte Rezension | 50 → 52 | 110,48 → 94,29 | 80,70–117,48 / 86,92–141,35 | 2 → 3 |
| Mission abschließen | 39 → 40 | 78,36 → 89,18 | 75,37–85,91 / 59,21–103,05 | 3 → 5 |
| Romantausch-Bündel | 30 → 30 | 45,88 → 41,94 | 44,09–48,20 / 34,36–50,22 | 3 → 3 |
| Veranstaltung wiederholt archivieren | 44 → 44 | 70,60 → 65,71 | 60,77–87,89 / 64,60–85,14 | 9 → 9 |
| Maddraxikon-Batch, fünf Beiträge | 137 → 137 | 177,72 → 183,70 | 170,82–187,67 / 168,99–197,32 | 47 → 47 |

Die zusätzlichen Abfragen bei Rezensionen und Missionen sichern Einmaligkeit
und atomare Abschlüsse. Der gemeinsame Writer fügt bei Bündeln, Veranstaltungen
und dem Maddraxikon-Batch keine Abfragen hinzu. Die überlappenden Zeitspannen
belegen keine allgemeine Backend-Beschleunigung. Der größte messbare
Geschwindigkeitsgewinn liegt beim initialen JavaScript.

Die Median-Gesamtzeiten der sperrenden SQL-Anweisungen betrugen in obiger
Reihenfolge vorher/nachher 1,21/2,06, 1,33/2,19, 2,30/3,89, 2,22/1,80,
6,74/6,71 und 32,16/34,05 ms. Diese Werte enthalten Ausführung und Transport;
sie sind **keine isolierten Sperrwartezeiten**. Konkurrenz und Sperrordnung
werden gesondert durch die MariaDB-Mehrprozess-Tests abgesichert.

### Frontend

Production-Builds mit Node 26.12.0 / Vite 8.3.4 und Chromium über Playwright
1.64.1. Je Szenario fünf frische Browserkontexte; Warmnavigation startet auf
`/mitglieder`, beim Mitglieder-Szenario auf `/`. Vier identische CPU-Kerne für
beide Messungen. Kartenkacheln und Städte-API sind stabilisierte Antworten;
die Mitgliederfixture besitzt Koordinaten und benötigt kein externes Geocoding.
Browserkontexte mit Request-Routing deaktivieren den HTTP-Cache; der
JavaScript-Modulcache bleibt während einer Warmnavigation bestehen.

| Initialer App-Importgraph | Vorher | Nachher | Änderung |
| --- | ---: | ---: | ---: |
| JavaScript, unkomprimiert | 605.551 B | 151.326 B | −75,01 % |
| JavaScript, normalisiertes gzip | 189.935 B | 49.695 B | −73,84 % |
| CSS einschließlich Leaflet, gzip | 60.546 B | 57.898 B | −4,37 % |
| JavaScript + CSS, gzip | 250.481 B | 107.593 B | −57,05 % |
| JavaScript-/CSS-Dateien | 5 | 2 | −3 |

Leaflet, Chart.js und die schweren RPG-Module liegen außerhalb des initialen
Graphs. Die folgende Größenangabe summiert **alle angeforderten gebauten
JS-/CSS-Dateien**, nicht nur `app.js`. Gzip wird für den Vergleich auf dieselben
Dateien angewendet; der PHP-Testserver selbst komprimiert seine Antworten nicht.

Kaltstart: Bereitschaftszeit als Median (Minimum–Maximum), in Millisekunden.
Bei Karten wird die Karteninstanz, bei Charts ein initialisiertes Canvas und
beim Editor die verfügbare Alpine-Factory geprüft.

| Seite | JS/CSS gzip kB vorher → nachher | Bereit ms vorher | Bereit ms nachher | JS-Ausführung ms vorher → nachher |
| --- | ---: | --- | --- | ---: |
| Startseite | 250,5 → 107,6 | 672 (623–814) | 649 (574–774) | 91,0 → 71,4 |
| Login | 250,5 → 107,6 | 383 (318–403) | 282 (272–322) | 95,2 → 62,9 |
| Mitgliederseite | 250,5 → 107,6 | 3.597 (3.367–4.193) | 3.544 (3.337–3.575) | 590,1 → 598,7 |
| Mitgliederkarte | 250,5 → 155,9 | 779 (747–2.414) | 655 (624–761) | 156,2 → 107,0 |
| Maddraxiversum | 261,6 → 165,4 | 698 (641–932) | 646 (561–851) | 129,5 → 96,9 |
| Statistik | 252,2 → 177,1 | 1.671 (1.637–2.125) | 1.322 (1.249–1.356) | 902,0 → 241,8 |
| Admin-Charts | 252,9 → 178,7 | 862 (765–869) | 910 (770–1.288) | 169,5 → 157,1 |
| Charaktereditor | 250,5 → 131,4 | 1.295 (1.276–1.362) | 1.196 (1.127–1.409) | 409,7 → 126,0 |

Warmnavigation: Größen zählen nur die zusätzlich geladenen Dateien. Der
vorherige Seitenaufruf kostete vor dem Refactoring bereits 250,5 kB, jetzt
107,6 kB. Beispielsweise kostet der Einstieg mit anschließendem Kartenbesuch
jetzt insgesamt 155,9 kB statt 250,5 kB.

| Ziel | Zusätzlich gzip kB vorher → nachher | Bereit ms vorher | Bereit ms nachher | JS-Ausführung ms vorher → nachher |
| --- | ---: | --- | --- | ---: |
| Mitgliederseite | 0 → 0 | 3.410 (3.037–3.627) | 3.259 (2.970–3.415) | 504,8 → 469,2 |
| Mitgliederkarte | 0 → 48,3 | 1.079 (941–1.126) | 944 (865–1.054) | 65,0 → 55,1 |
| Maddraxiversum | 11,1 → 57,8 | 939 (912–1.198) | 956 (760–1.159) | 61,2 → 53,9 |
| Statistik | 1,8 → 69,5 | 1.557 (1.428–1.593) | 1.667 (1.498–1.968) | 558,0 → 207,4 |
| Admin-Charts | 2,4 → 71,1 | 1.132 (1.068–1.236) | 1.114 (992–1.124) | 81,0 → 84,7 |
| Charaktereditor | 0 → 23,8 | 1.530 (1.415–1.617) | 1.438 (1.258–1.933) | 320,1 → 82,2 |

Bedarfsabhängiges Laden bringt zusätzliche Requests beim ersten Featurebesuch.
Die gemessene Bereitschaft der Admin-Charts beim Kaltstart und der Statistik
bei Warmnavigation war etwas langsamer. Die geringere initiale Datenmenge ist
deterministisch; eine Beschleunigung jeder Navigation lässt sich nicht ableiten.
Die Maddraxiversum-Zeit beschreibt nur die sichtbare Karte: Der separat
behobene Missionsdialogfehler bestand im Ausgangsstand.

| Kaltstart | FCP ms vorher → nachher | LCP-Stichprobe ms vorher → nachher |
| --- | ---: | ---: |
| Startseite | 244 → 228 | 624 → 632 |
| Login | 208 → 184 | 252 → 196 |
| Mitgliederseite | 740 → 700 | 1.112 → 1.048 |
| Mitgliederkarte | 352 → 288 | 428 → 364 |
| Maddraxiversum | 308 → 300 | 396 → 308 |
| Statistik | 480 → 460 | 540 → 460 |
| Admin-Charts | 348 → 380 | 508 → 472 |
| Charaktereditor | 368 → 376 | 448 → 428 |

FCP/LCP sind lokale Stichproben bis zum Bereitschaftsmarker, keine abschließend
erhobenen Feldwerte. Alle Einzelläufe, Ressourcenmengen und SQL-Messungen liegen
in [den Messdaten](measurements/refactoring-baxx-javascript.json). Die Browser-
Messung ist mit `npm run test:e2e:benchmark -- tests/e2e/feature-performance.benchmark.js`
wiederholbar; die zugehörige Fixture akzeptiert ausschließlich die isolierte
Playwright-SQLite-Datenbank.

### Testabnahme

| Prüfung | Ergebnis |
| --- | --- |
| PHP Unit/Feature/Integration | 3.923 unterschiedliche erfolgreiche Fälle, 43 erwartete plattform-/datenbankabhängige Skips. Vollständige Suite in vier isolierten Gruppen; drei anschließend ergänzte Grenzfälle separat ausgeführt. |
| MariaDB | Alle drei zentralen Suiten: 381 + 21 + 8 = 410 erfolgreiche Tests. Zusätzlich 20 erfolgreiche Fälle mit den neuen Grenzfalltests und Konkurrenztests. |
| Vitest | 39 Dateien, 552 erfolgreiche Tests; keine Async-Leaks. Ausgangsstand: 34 Dateien, 510 erfolgreiche Tests. |
| Node | Vollständige Suite mit 85 erfolgreichen Fällen, einschließlich Sourcemap-Prüfung und isolierter Browseraufrufe. |
| Architektur / Pest Browser | 6 Architekturtests und 1 Browserfall mit 77 Assertions erfolgreich. |
| PHPStan / Rector / Profanity / Pint | Erfolgreich; keine neuen Ausnahmen oder abgesenkten Regeln. |
| Build | Production-Build und Prüfung des initialen Importgraphs einschließlich Sourcemaps erfolgreich. |
| Neue Feature-Browsermatrix | Alle 12 Fälle auf Chromium, Firefox und WebKit erfolgreich. |
| Chromium gesamt | 271 erfolgreiche Fälle, 2 vorgesehene Skips; das erste Projekt des kombinierten Laufs ist vollständig abgeschlossen. |
| Firefox gesamt | Unabhängiger Lauf mit frischer Datenbank bis einschließlich Fall 254 von 273 ohne gemeldeten Fehler; bei Fall 255 durch Ausfall der Docker-Engine blockiert. Kein vollständiger erfolgreicher Abschluss behauptet. |
| WebKit gesamt | Bis einschließlich Fall 257 von 273 zehn Fehler; bei Fall 258 durch denselben Engine-Ausfall blockiert. Einzelheiten und Vergleichsläufe unten. |

Die abschließende vollständige Browserabnahme bleibt offen: Docker Desktop
antwortete während der letzten Läufe auf `/_ping` mit HTTP 500; anschließend
liefen auch zeitlich begrenzte Status- und Stop-Abfragen in Timeouts. Weitere
Vergleichscontainer konnten nicht starten. Die gemeinsam genutzte
Entwicklungsumgebung wurde nicht neu gestartet. Auch das Beenden der beiden
Refactoring-Browsercontainer war über die ausgefallene Engine nicht möglich.
Nach Wiederherstellung von Docker sind die vollständigen Browserprojekte mit
dem korrigierten Teststarter erneut auszuführen.

Der WebKit-Gesamtlauf ist nicht vollständig grün. Gezielte Vergleichsläufe mit
dem archivierten Ausgangsstand und den jeweiligen Production-Assets zeigen
dieselben sieben problematischen bestehenden Testfälle in beiden Ständen:

- `char-editor.spec.js`: die beiden ausführlichen Regelhilfe-Tests für Vor- und
  Nachteile erreichen Zeitlimits beziehungsweise verlieren während
  Pointeraktionen ihren geöffneten Hilfetext.
- `modal-preview.spec.js`: die vollständige Modal-Vorschau überschreitet ihr
  Zeitlimit bei Scroll-/Sichtbarkeitsprüfungen.
- `navigation-search.spec.js`: Gastnavigation, der mobile `Meta+k`-Einstieg und
  beide mobilen Theme-Prüfungen scheitern bei Dialog-/Fokusprüfungen.

Die Morlock-Speicherprüfung und der mobile `Control+k`-Test, die im langen
WebKit-Lauf ebenfalls fehlgeschlagen waren, sind in den gezielten
Vergleichsläufen jeweils in beiden Ständen erfolgreich. Die
Editor-Vergleichsgruppe ergibt vorher/nachher jeweils 3 erfolgreiche
und 2 fehlgeschlagene Fälle, die Modal-/Suchgruppe jeweils 1 erfolgreichen und
5 fehlgeschlagene Fälle. Diese Befunde begrenzen die vollständige WebKit-Abnahme;
es wurden dafür keine Assertions abgeschwächt oder neue Skips eingeführt.
Die vorhandene CI beschränkt ihre Browsermatrix bereits auf Chromium und Firefox.

Ein zehnter WebKit-Fehler betraf den Admin-Menüdurchlauf in `ux-pages.spec.js`
mit einem Navigationstimeout auf `/admin/messages`. Dessen separater
Vorher-/Nachher-Vergleich konnte wegen des Engine-Ausfalls nicht mehr starten;
seine Ursache ist daher offen und wird nicht als bestätigter Altfehler gewertet.

Die PHP-Zeilenabdeckung beträgt **87,09 %**, vollständig abgedeckte Methoden
**70,25 %**. Alle zehn neuen Baxx-Kernklassen einschließlich der Aktionen und
`MissionCompletionService` erreichen **226 von 226 ausführbaren Zeilen**.
Die JavaScript-Zeilenabdeckung steigt im gleichen V8-Messverfahren von
**84,83 % auf 87,55 %**, Funktionsabdeckung von **86,67 % auf 88,09 %**,
Branch-Abdeckung von **71,88 % auf 74,98 %**.

Beide vorhandenen Coverage-Prüfungen bestehen global, pro Datei und für
unveränderte Quellzeilen. Die Baselines bleiben unverändert. PHP wurde für
diesen verbindlichen Vergleich mit Xdebug 3.5.3 gemessen; der geschützte
Projektfloor beträgt 85,97 % Zeilen / 68,15 % Methoden. Der frühere lokale
PCOV-Vorherlauf wird wegen des anderen Treibers nicht als direktes Xdebug-Delta
ausgewiesen. Der lokale Einzellauf bestand fachlich, überschritt aber beim
Export das 512-MB-Limit; vier isolierte Testgruppen mit je 1 GiB und das vorhandene
Merge-Werkzeug lieferten anschließend den vollständigen erfolgreichen Bericht.

## Auslieferung und Rückweg

Anwendungscode, versionierte Assets und Manifest gemeinsam ausliefern. Bereits
veröffentlichte Chunks während laufender Sitzungen verfügbar halten. Bei einem
Rollback den passenden Code- und Assetstand gemeinsam zurücksetzen. Es gibt
keine neue Datenbankmigration; bereits erfolgte fachliche Buchungen bleiben
bestehen. Ein Import-Reset mit vorhandenen historischen Buchungen erfordert
weiterhin eine gesondert nachvollziehbare Quellenzuordnung.

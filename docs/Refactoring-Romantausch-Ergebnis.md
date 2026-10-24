# Ergebnis: Gemeinsame Romantausch-Workflows

Stand: 24. Oktober 2026.

## Architektur und Kompatibilität

Alle elf bisherigen HTTP-Schreibendpunkte und die Livewire-Aktionen delegieren an
gemeinsame Workflows unter `app/Actions/Romantausch`. Namen, URLs, Parameter und
HTTP-Methoden der Routen sind erhalten. Die Workflows erhalten den Akteur
ausdrücklich und enthalten keine Session-, Redirect- oder Livewire-Abhängigkeiten.

| Baustein | Verantwortung |
| --- | --- |
| `OfferWorkflow` | Einzelangebot erstellen, bearbeiten und löschen; aktuelle Eigentümer-/Statusprüfung, Buchzuordnung, Fotos, Aktivität und Vergütung. |
| `RequestWorkflow` | Gesuch erstellen, bearbeiten und löschen mit demselben Vertrag für beide Transportwege. |
| `BundleWorkflow` | Stapel einschließlich Hinzufügen/Entfernen, gemeinsam verwendeter Fotos und Vergütung neuer Angebote. |
| `SwapWorkflow` | Teilnehmerbestätigung, berechtigter manueller Abschluss, Wiederholungsprüfung, Aktivität und tatsächliches Vergütungsergebnis. |
| `InputRules` | Gemeinsame Regeln, Fehlermeldungen, Serien und Normalisierung für FormRequests, Livewire und direkte Workflow-Aufrufe. |
| `InteractsWithRomantauschForm` | Gemeinsame Buch-/Serienoptionen sowie transportbezogene Rückmeldungen und Fehlerbehandlung. |
| `MutationLock` | Einheitliche Eigentümersperren und begrenzte Wiederholung von Datenbanktransaktionen. |
| `BookPhotoService` | Uploadprüfung, Zusammenführen der Pfade, Rollback-Cleanup und Entfernen unreferenzierter Dateien nach Commit. |
| `CommittedMatching` | Matching nach Quell-Commit; gemeinsame Kandidatenvorprüfung bei Stapeln und getrennte Rückmeldung bei Matching-Ausfall. |
| `SwapMatchingService` | Gesperrte reziproke Zuordnung und Benachrichtigung nach Match-Commit. |
| `SourceDeletion` | Zugeordnete Swaps entfernen und aktive Abbrüche protokollieren. |

`BundleService` enthält noch Nummernparser, Darstellung und Buchabfragen. Die
bisherigen zweiten Speicherabläufe wurden entfernt. Unbenutzte HTTP-gebundene
Foto-Hilfsmethoden entfielen ebenfalls. `StoreBookOfferRequest::ALLOWED_TYPES`
bleibt als kompatibler Zugang zu den gemeinsamen Serien erhalten.

Die bestehenden Baxx-Services, Sonderaktionen, Fortschritte und Mitglieder-Wallets
werden weiterverwendet. Bearbeitungen zählen nicht als Neuerstellung; Stapeländerungen
vergüten ausschließlich hinzugefügte Angebote. Es gibt keine Datenbankmigration
und keine Neuberechnung historischer Guthaben. Die bisherige Löschbarkeit
abgeschlossener Quellen bleibt einschließlich erhaltener Baxx bestehen.

## Transaktions- und Sperrvertrag

Jede Änderung sperrt zuerst die betroffenen Quelleneigentümer in `users` nach
aufsteigender ID. Matching und Abschluss sperren beide Teilnehmer. Anschließend
werden benötigte Angebote, Gesuche und Swaps in dieser Tabellenreihenfolge und
innerhalb jeder Tabelle nach ID gesperrt. Die bestehenden Baxx-Fortschrittssperren
folgen danach. Löschen ändert die eigene Quelle und deren Zuordnung; dabei wird
keine zusätzliche Eigentümersperre nach einer Quellensperre erworben.

Status und Zuordnung werden mit aktuellen sperrenden Leseoperationen geprüft.
Das verhindert Entscheidungen auf veralteten MariaDB-`REPEATABLE READ`-Snapshots.
Auch Neuerstellungen verwenden die Eigentümersperre, damit parallele Einreichungen
die Baxx-Schwellen konsistent verarbeiten.

Quelle, Aktivität, Fortschritt und Buchungen werden gemeinsam gespeichert. Beim
Tauschabschluss umfasst die Transaktion zusätzlich Bestätigungsflags, Abschlusszeit
und beide Quellstatus. Wiederholungen liefern den bestehenden Abschluss ohne neue
Buchung oder Abschlussaktivität. Ein vorhandener passender Swap wird wiederverwendet.

Uploads erfolgen außerhalb wiederholbarer Datenbankarbeit. Bei einem Fehlschlag
werden nur neue Dateien dieses Versuchs entfernt. Der Rollback-Hook für eine
umschließende Transaktion wird erst nach erfolgreicher Rückkehr der wiederholbaren
Transaktion registriert; interne Deadlock-Wiederholungen behalten ihre Uploads.
Alte Dateien werden nach Commit erst entfernt, wenn kein verbleibendes Angebot
sie referenziert. Das berücksichtigt auch geteilte Stapelfotos.

Matching beginnt nach dem Quell-Commit und erstellt beide reziproken Swaps in
einer eigenen Transaktion. Benachrichtigungen werden nach deren äußerstem Commit
eingereiht. Ein Matching-Fehler erhält die gespeicherten Einträge und Fotos und
führt zu einem Hinweis statt einer Aufforderung, denselben Eintrag erneut anzulegen.
Queue-/Dateisystemfehler nach Commit werden protokolliert; sie stellen die
erfolgreiche Datenbankänderung nicht als zurückgerollt dar.

## Nachgewiesene Korrekturen

| ID | Verhalten nach der Änderung | Nachweis |
| --- | --- | --- |
| K01 | Beim Speichern inzwischen zugeordnete oder abgeschlossene Angebote/Gesuche/Stapel werden abgewiesen. | Veraltete Livewire-Formulare und aktuelle HTTP-Prüfungen in `RomantauschWorkflowTest`. |
| K02 | Manueller Abschluss erfordert Admin/Vorstand im Mitglieder-Team. Eine Rolle ausschließlich in einem anderen Team genügt nicht. Ein anderes aktuell gewähltes Team entzieht berechtigten Vereinsrollen den Zugriff nicht. | Rollenmatrix für HTTP und Livewire sowie Tests beider Teamwechsel-Fälle. |
| K03 | Außenstehende können keine Bestätigung auslösen. Wiederholte Bestätigungen verändern abgeschlossene Tauschaktionen nicht. | Fremde Bestätigung über beide Einstiege; Wiederholung und konkurrierende Bestätigungen. |
| K04 | Fehler bei Upload, Datenbankupdate oder äußerem Rollback hinterlassen keine neuen Dateien; vorhandene Fotos bleiben erhalten. | Teilupload, fehlende/beschädigte Bilddatei, Update-Rollback, äußerer Rollback und Root-Transaktionsretry. |
| K05 | Stapelaktivität und Vergütung sind Teil der Speicherung; Matching und Mails warten auf den jeweiligen Commit. | Fehlgeschlagene Aktivität und Vergütung, äußere Rollbacks sowie getrennte Matching-Fehler. |
| K06 | Doppelzuordnung und erneute Vergütung werden verhindert; ein manueller Abschluss verwendet einen bestehenden passenden Swap. | Wiederholungen, unpassende/anderweitig zugeordnete Paare und unabhängige MariaDB-Worker. |
| K07 | Einzel- und Stapellöschung behandeln aktive Zuordnungen und Fotos gemeinsam; weiter referenzierte Fotos bleiben bestehen. | Löschtests einschließlich geteilter Fotopfade und erhaltener historischer Baxx. |
| K08 | Hinweistext verwendet aktuelle Regeln; der Abschluss-Toast nennt tatsächlich vergebene Baxx bzw. ausbleibende Vergütung. | Sonderaktionen, deaktivierte Regel, wiederholter Abschluss und dynamischer Informationstext. |

K02 ist eine beabsichtigte Verhaltenskorrektur des bisher unzureichend geschützten
HTTP-Endpunkts und des bisherigen Teambezugs. Kassenwarte erhalten dadurch keine
zusätzliche Berechtigung zum manuellen Abschluss. Transportbedingte Fehler bleiben
für HTTP als Formularfehler/Redirect und für Livewire als Feldfehler/Toast sichtbar.
Die vorhandene Fehlerzusammenfassung zeigt auch fachliche Statusfehler an.

## Tests und Abdeckung

| Prüfung | Ergebnis |
| --- | --- |
| Gesamte PHP-Suite | 3.963 bestanden, 17.630 Assertions; 51 umgebungsabhängig übersprungen. |
| Abschließender Fachlauf mit Xdebug | 249 bestanden, 848 Assertions; acht MariaDB-Fälle im SQLite-Lauf übersprungen. |
| Echte MariaDB-Konkurrenztests | Alle acht bestanden, 47 Assertions, mit `--fail-on-skipped`. |
| Chromium | Alle 24 Romantausch-Browserfälle bestanden. |
| Architekturregeln | Alle sechs bestanden, 14 Assertions. |
| Laravel Pint | Prüfung der betroffenen PHP-Dateien bestanden. |
| Assetbuild | Vite-Build und Prüfung des initialen Importgraphen bestanden. |

Die abschließende Matching-Optimierung ist im letzten Fachtestlauf und den erneut
bestandenen MariaDB-Tests enthalten. Für die zuletzt vereinfachte Regelanzeige
wurden die Anzeige- und Workflow-Regressionen nochmals ausgeführt.

| Bereich | Abgedeckte / ausführbare Zeilen | Zeilenabdeckung |
| --- | --- | --- |
| Vier neue Workflows | 183 / 183 | 100 % |
| Alle zwölf neuen Workflows und gemeinsamen Bausteine einschließlich Formular-Trait | 276 / 276 | 100 % |
| Überarbeiteter `SwapMatchingService` | 45 / 46 | 97,83 % |
| Überarbeiteter `BookPhotoService` | 74 / 88 | 84,09 % |

Die Coverage-Zahlen beziehen sich auf ausführbare Zeilen der jeweils ausgewiesenen
PHP-Anwendungsklassen im Fachtestlauf mit Xdebug. Die JSON-Messdatei enthält die
Zähler je Datei. Die neuen Tests ergänzen bestehende Tests; insbesondere wurden
Autorisierung, Statuswechsel, Rollback und echte Konkurrenz getestet.

Die acht MariaDB-Fälle verwenden unabhängige Prozesse mit einer Startbarriere:
Neuerstellungen an einer Baxx-Schwelle, paralleles Matching, parallele Bestätigungen,
Bestätigung gegen manuellen Abschluss, Matching gegen Bearbeiten/Löschen sowie
Stapeländerung gegen Matching/Löschen. Die Root-Retry-Prüfung verwendet eine eigene
wegwerfbare SQLite-Datenbank ohne umschließende Testtransaktion.

## CI-Nachprüfung der Coverage

Im ersten CI-Lauf nach dem Refactoring bestanden die Tests, aber der Schritt
`Reject coverage regressions` scheiterte: In sechs bestehenden Dateien sank die
relative Zeilenabdeckung, in zwei davon zusätzlich die Methodenabdeckung. Die
oben ausgewiesene vollständige Abdeckung der neuen Bausteine ersetzt diesen
Vergleich nicht. Die geschützte Baseline und der CI-Prüfer bleiben unverändert.

Zwölf zusätzliche Testfälle prüfen das Öffnen bereits zugeordneter oder
abgeschlossener Angebote und Gesuche, die drei Livewire-Ladeplatzhalter,
persönliche Sammlungen ohne Anmeldung, die Kompatibilität der veröffentlichten
Serien mit Formularen und HTTP, ungültige Bereichsgrenzen und Zustandswerte sowie
den Fall ohne verfügbare Übersetzungen.

Die drei betroffenen Testdateien bestehen mit 75 Tests und 286 Assertions.
Die Original-Coverage-Artefakte beider erfolgreichen Test-Shards aus
[CI-Lauf 40093309277](https://github.com/McNamara84/omxfc-vereinswebseite/actions/runs/40093309277)
wurden erneut mit dem bestehenden PHP-Berichtswerkzeug ausgewertet. Damit lässt
sich der ursprüngliche Fehler lokal exakt reproduzieren. Für die sechs Dateien
wurden die zusätzlich gemessenen Treffer ergänzt; Quellhashes und
Coverage-Positionen müssen beim Zusammenführen übereinstimmen. Alle übrigen
Dateien behalten ihre unveränderten CI-Messwerte. Das ist eine inkrementelle
Nachprüfung mit den vollständigen CI-Berichten und dem zusätzlichen Testlauf,
kein neuer lokaler Gesamtlauf.

| Datei | Zeilen / Methoden nach ergänzenden Tests |
| --- | --- |
| `StoreBookOfferRequest` | 4 / 4 Zeilen, 4 / 4 Methoden |
| `RomantauschIndex` | 105 / 105 Zeilen, 21 / 21 Methoden |
| `RomantauschOfferForm` | 29 / 29 Zeilen, 10 / 10 Methoden |
| `RomantauschRequestForm` | 23 / 23 Zeilen, 6 / 6 Methoden |
| `BundleService` | 76 / 76 Zeilen, 8 / 8 Methoden |
| `RomantauschInfoProvider` | 16 / 16 Zeilen, 1 / 1 Methode |

`tools/testing/check-coverage.mjs` besteht damit einschließlich des PR-Vergleichs
gegen die geschützte Baseline aus dem Basis-Commit
`b6c85f2740d493d1596f16aaa13c191534672b06`: global, pro Datei und an unveränderten
Quellpositionen. Der Korrektur-Commit ändert ausschließlich Tests und diesen
Nachweis. Ein neuer GitHub-CI-Lauf erfolgt nach dem Push.

## Erste Code-Review-Runde

Alle vier Befunde wurden bestätigt und mit Regressionstests behoben:

- Stapelformular und Workflow verwenden dieselbe deduplizierte Fotomenge aller
  eigenen Angebote im Stapel. Anzeige, Entfernen und Restkontingent stimmen
  damit überein; das Kontingent aktualisiert sich beim Markieren zum Entfernen.
  Eine durch Einzelbearbeitungen entstandene Überschreitung lässt sich im
  Formular vollständig bereinigen, ohne Fotos stillschweigend zu verwerfen.
- Verschwindet eine Quelle zwischen Swap-Abfrage und Laden der Beziehungen,
  bricht die Bestätigung mit HTTP 404 ab, bevor Eigentümer-IDs gelesen werden.
  Die anschließenden sperrenden Abfragen bleiben erhalten.
- Foto-Cleanup prüft jeden unterschiedlichen entfernten Pfad mit
  `whereJsonContains(...)->exists()` in der Datenbank. Die Anfrage lädt keine
  Fotolisten der gesamten Börse. Auch abgeschlossene Angebote anderer Mitglieder
  schützen gemeinsam referenzierte Fotos weiterhin vor dem Löschen.
- Beide Abbruchaktionen erscheinen im Dashboard als „Tauschzuordnung aufgehoben“
  mit der zutreffenden Ursache. Tests führen das Löschen von Einzelangebot,
  Gesuch und Stapel über HTTP und Livewire bis zur tatsächlichen Feed-Darstellung aus.

Die Regressionstests reproduzieren die vier ursprünglichen Fehler. Nach der Korrektur
bestehen 275 Fachtests mit Xdebug und 1.052 Assertions. Die dort unter SQLite
übersprungenen acht Konkurrenztests wurden zusammen mit allen 14 neuen Fällen
unter MariaDB ausgeführt: 22 Tests, 172 Assertions, keine übersprungenen Tests.
Die JSON-Prüfung wird mit leerer und mit 200 zusätzlichen abgeschlossenen
Angeboten ausgeführt und prüft auch Dateinamen mit Anführungszeichen und
ähnliche, aber unterschiedliche Pfade. Die neuen Fälle sind in der
MariaDB-CI-Konfiguration enthalten. Pint und alle sechs Architekturregeln bestehen.

Der geschützte CI-Coverage-Vergleich besteht weiterhin. Für die vier geänderten
Anwendungsklassen wurden die Messwerte durch den neuen Xdebug-Lauf ersetzt;
566 unveränderte Dateien behalten die bisherigen Nachweise. Ihre Quellhashes
wurden vor dem Vergleich geprüft. `BundleWorkflow` und `SwapWorkflow` erreichen
weiterhin jeweils 100 % Zeilen- und Methodenabdeckung. Der Foto-Service erreicht
75 / 89 Zeilen, das Stapelformular 42 / 47 Zeilen. Baseline und CI-Prüfer wurden
nicht verändert; diese Nachprüfung ist weiterhin inkrementell.

## Zweite Code-Review-Runde: Commit-Grenzen im Test

Die beiden Hinweise zur angeblich bis zum Testende ausbleibenden Foto-Löschung
treffen auf den installierten Laravel-Testbetrieb nicht zu. `RefreshDatabase`
installiert `Illuminate\Foundation\Testing\DatabaseTransactionsManager`.
Dessen `afterCommitCallbacksShouldBeExecuted()` führt die Callbacks bereits beim
Rücksprung auf Transaktionsebene 1 aus; `callbackApplicableTransactions()` nimmt
die äußere Testtransaktion aus. Der reguläre
`Illuminate\Database\DatabaseTransactionsManager` verwendet dagegen Ebene 0.
Die vier beanstandeten Fälle bestehen unverändert unter SQLite und MariaDB,
jeweils mit 58 Assertions. Ein manuelles Commit der RefreshDatabase-Transaktion
ist dafür nicht erforderlich und würde die Isolation dieser Tests verändern.

Zur zusätzlichen Prüfung echter Commit-Grenzen verwendet
`tests/Integration/RomantauschPhotoCommitTest.php` eine eigene SQLite-Datenbank
ohne `RefreshDatabase`. Die Tests prüfen den regulären Transaktionsmanager und
den PDO-Transaktionszustand ausdrücklich. Je ein Commit- und Rollback-Fall sichert
Stapel-Fotoersetzung, verzögerte Referenzabfragen und das Löschen des letzten
Verweises auf ein gemeinsam verwendetes Foto ab. Vor dem äußersten Commit
bleiben die alten Dateien erhalten. Bei Rollback bleiben Datenbankreferenzen
und alte Fotos erhalten, neue Uploads werden entfernt; ein späterer Commit darf
keine verworfenen Cleanup-Callbacks nachholen.

Alle sechs neuen Fälle bestehen mit 112 Assertions. Zusammen mit den bestehenden
Workflow-, Review- und Retry-Tests bestehen 68 Tests mit 471 Assertions; Pint
besteht für alle drei geänderten PHP-Testdateien. Anwendungscode, Coverage-Baseline
und CI-Konfiguration bleiben unverändert.

## Messungen

Ausgangscode: Commit `b6c85f2740d493d1596f16aaa13c191534672b06`.
Vorher und nachher laufen dieselben HTTP- und Matching-Fälle mit fiktiven Daten
unter PHP 8.5.12, SQLite in-memory und ohne Coverage-Instrumentierung im isolierten
Docker-Teststack. Ein Aufwärmlauf wird verworfen, fünf Wiederholungen werden
ausgewertet. Stapel enthalten 200 Bücher. Der Fall mit 20 Kandidaten verwendet
20 passende Gesuche desselben Gegenübers ohne reziprokes Angebot.

| Vorgang | Median vorher / nachher (ms) | SQL vorher / nachher |
| --- | --- | --- |
| Einzelangebot erstellen | 6.05 / 7.26 | 14 / 17 |
| Einzelangebot bearbeiten | 5.33 / 6.29 | 11 / 14 |
| Gesuch erstellen | 6.34 / 7.07 | 14 / 17 |
| Gesuch bearbeiten | 4.59 / 6.07 | 9 / 14 |
| Stapel mit 200 Büchern erstellen | 142.97 / 74.50 | 418 / 220 |
| Stapel mit 200 Büchern bearbeiten | 70.89 / 71.22 | 208 / 209 |
| Abschließende Teilnehmerbestätigung | 12.60 / 12.90 | 39 / 41 |
| Manueller Tauschabschluss | 14.68 / 13.21 | 38 / 43 |
| Matching ohne Gegenkandidaten | 0.48 / 0.74 | 1 / 3 |
| Matching mit 20 Gesuchen desselben Gegenübers | 15.89 / 2.88 | 41 / 7 |

Beim Stapel wird die Buchzuordnung gesammelt geladen und eine gemeinsame
Kandidatenvorprüfung verwendet. Das Matching prüft ein fehlendes reziprokes Inventar
je Gegenüber innerhalb eines Aufrufs einmal. Dieser kurzlebige Zustand wird nicht
über Aufrufe hinweg gecacht; neue Änderungen starten ihr eigenes Matching.

Die zusätzlichen Abfragen bei Einzeländerungen und Abschlüssen dienen aktuellen
Zustands- und Berechtigungsprüfungen sowie dem Sperrvertrag. Laufzeiten sind lokale
Mikromessungen einschließlich Test-HTTP-Kernel; Produktionsantwortzeiten hängen
zusätzlich von Datenbestand, MariaDB, Netzwerk und Dateisystem ab.

Rohwerte, Speicher-Spitzenwerte und Coverage-Zähler:
[`measurements/refactoring-romantausch.json`](measurements/refactoring-romantausch.json).
Der reproduzierbare Messfall liegt unter
[`tests/Performance/RomantauschBenchmark.php`](../tests/Performance/RomantauschBenchmark.php).

## Gezielte Wiederholung

```powershell
docker compose -f docker-compose.tests.yml run --rm -T --no-deps php php vendor/bin/pest --filter='Romantausch|BundleOffer|BookSwap|BookPhoto|BundleService|SwapMatchingService|ActivityFeed' --compact
docker compose -f docker-compose.tests.yml up -d --wait db
docker compose -f docker-compose.tests.yml run --rm -T --no-deps php php vendor/bin/pest --configuration=phpunit.mariadb.xml --filter=RomantauschMariaDbConcurrencyTest --fail-on-skipped --compact
docker compose -f docker-compose.tests.yml run --rm -T --no-deps browser node node_modules/playwright/cli.js test tests/e2e/romantausch-single-offer.spec.js tests/e2e/romantausch-bundle.spec.js --project=chromium
```

Für die Messung explizit `tests/Performance/RomantauschBenchmark.php` mit PHPUnit
aufrufen und `ROMANTAUSCH_BENCHMARK_OUTPUT` auf den gewünschten JSON-Ausgabepfad
setzen. Die normale Testsuite startet den Benchmark nicht.

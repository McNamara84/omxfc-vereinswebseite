# Ergebnis: Gemeinsame Rollen- und Mitgliedschaftsauflösung

Stand: 25. Oktober 2026.

## Verhalten und Architektur

`app/Services/Memberships/MembershipContext.php` bündelt die allgemeine
Rollenauflösung des eigenen Anwendungscodes. Die bisherigen Methoden in `User`,
`Team` und `UserRoleService` sind kompatible Adapter. Die Navigation bezieht ihre
Rollen ebenfalls aus dem Kontext und nutzt ihre ohnehin benötigte Teamliste als
gemeinsame Datenquelle.

`MembershipSnapshot` enthält Benutzer-ID, Team-ID, Existenz der Pivot-Zeile,
rohen Rollenwert und den daraus abgeleiteten `Role`-Enum. Zusätzlich unterscheidet
er, ob die Existenz des Benutzers beim Lesen geprüft wurde. Das erhält den
bisherigen Unterschied zwischen dem direkten Pivot-Zugriff des
`UserRoleService` und dem Benutzer-Join in `Team::hasUserWithRole()`, auch bei
verwaisten Legacy-Zuordnungen.

Die Wiederverwendung berücksichtigt Verbindung, Datenbank, Verbindungsinstanz,
Benutzer-ID und Team-ID. Auch fehlende Mitgliedschaften und negative Ergebnisse
werden innerhalb des gültigen Kontexts wiederverwendet. Gates, Policies,
Eigentümerschaft und Entscheidungen über einzelne Fachobjekte werden nicht gecacht.

Der Dienst ist im Container `scoped`. Die globale Middleware
`ResetMembershipContext` setzt ihn vor und nach HTTP-Requests zurück. Ergänzende
Listener decken Livewire-Updates, Queue-Jobs, Artisan-Ausführungen sowie
Transaktionsbeginn, Commit und Rollback ab. In Livewire wird kein Kontext als
öffentliche Komponentenproperty serialisiert. Der Kontext benutzt weder einen
langlebigen Singleton noch Session- oder verteilte Rollencaches.

## Aktualität und Invalidierung

Wiederverwendung gilt für lesende Requests außerhalb von Transaktionen.
Schreibrequests einschließlich Livewire-POSTs lesen konservativ frisch. Die
Prüfung des Request-Typs erfolgt auch im Kontext selbst, weil Livewires
Komponententests HTTP-Middleware absichtlich umgehen. Diese Präzisierung des
Entwurfs stellt sicher, dass eine frühe Anzeigeprüfung keine spätere Mutation
mit einem alten Rollenwert autorisiert. Die Laufzeitoptimierung konzentriert
sich damit auf die häufigen lesenden Seitenaufrufe.

`freshForUserInTeam()` liest ausdrücklich neu; `runFresh()` kapselt mehrere
frische Prüfungen mit Rücksetzung auch im Fehlerfall. Die Sonderaktionsverwaltung
verwendet letzteren Weg zusätzlich bei direkten Service-Aufrufen außerhalb von HTTP.
Innerhalb einer Datenbanktransaktion wird kein Rollen-Snapshot wiederverwendet.
Frisches Lesen ersetzt keine Datenbanksperren: Die vorhandenen
`MembersTeamMembershipLock`- und Fachsperren bleiben maßgeblich.

Die eigenen Jetstream-Relationen verwenden bereits das Pivot-Modell `Membership`.
Dessen synchrone Speicher- und Löschereignisse invalidieren auch `attach`,
`updateExistingPivot`, `syncWithoutDetaching` und `detach`. Benutzer- und
Teamereignisse ergänzen Teamwechsel, Neuanlage, Namens- und Eigentümeränderung
sowie Löschungen. `Team::clearMembersTeamCache()` verwirft ebenfalls den
Request-Kontext.

Eine `WeakMap` verfolgt beobachtete User- und Teaminstanzen, ohne diese dauerhaft
am Leben zu halten. Bei Invalidierung werden Rollenwerte und geladene
Team-/Eigentümerrelationen der kleinen aktuellen Instanzmenge verworfen. Die
Invalidierung ist bewusst konservativ für den gesamten Request-Kontext; sie
erzeugt keine zusätzlichen Datenbankabfragen.

Teamwechsel und strukturelle Teamänderungen werden auch an anderen bereits
geladenen Instanzen derselben Identität sichtbar. Bei Rollback werden diese
strukturellen Attribute aus der wieder gültigen Datenbankversion hergestellt.
Die ursprünglichen Werte des gerade gespeicherten Modells bleiben während seiner
Ereignisverarbeitung erhalten, damit bestehende Lifecycle-Listener weiterhin
Änderungen und frühere Teamnamen erkennen können.

Bei Builder-/SQL-Mutationen oder stillen Änderungen ohne Modellereignisse gilt:
Innerhalb einer Transaktion übernehmen die Transaktionsgrenzen die Rücksetzung.
Außerhalb davon muss der aufrufende Code `invalidateUser()`, `invalidateTeam()`
oder `reset()` ausdrücklich aufrufen. Fachliche Batch- und Sperrabfragen werden
nicht mit Einzelabfragen in Schleifen ersetzt.

## Inventar der Zugriffswege

| Bereich | Umsetzung / erhaltene Besonderheit |
| --- | --- |
| `User::role`, `hasRole`, `hasAnyRole`, `hasVorstandRole` | Gemeinsamer Snapshot für das aktive Team; bisherige Rollenmenge bleibt maßgeblich. |
| Mitglieder-Team-Methoden in `User` | Ausdrücklicher Mitglieder-Team-Bezug über den vorhandenen Teamresolver. |
| `UserRoleService` und `MembersTeamAware` | Gemeinsame Auflösung; nullable Adapter und `ModelNotFoundException`-Verträge bleiben erhalten. `new UserRoleService` funktioniert weiterhin. |
| `Team::hasUserWithRole` | Gemeinsame Datenquelle; kein Eigentümerbonus. SQL-Fallback für nichtkanonische Rollenstrings und unbekannte Benutzerexistenz. |
| Namensbasierte Mitgliedschaft und Eigentümerschaft | Gemeinsame requestlokale SQL-Ergebnisse; SQL-Vergleich von Teamnamen bleibt erhalten. |
| Navigation, Suche, View-Composer und Blade-Aufrufer | Indirekte Adapter und zentrale `navigationRoles`-Auflösung; vorhandene Teamlisten werden weiter genutzt. Bei mehrfachen Legacy-Namen bleibt der erste passende Navigationseintrag maßgeblich. |
| Rollen-Middleware, Login, FormRequests, Gates und Policies | Nutzen weiterhin ihre bestehenden Methoden, die nun an den Kontext delegieren. Der direkte Anwärter-SQL-Zugriff in `LoginResponse` wurde angeschlossen. |
| Controller, Livewire und Fachservices | Bestehende Aufrufschnittstellen sind indirekt migriert; fachliche Bedingungen bleiben beim Aufrufer. Schreibrequests und transaktionale Entscheidungen verwenden frische Daten. |
| Jetstream-Berechtigungen | Native Owner-Sonderfälle, Permissions und Sanctum-Tokenbegrenzungen bleiben erhalten; kein Vendor-Code geändert. |

Begründete direkte SQL-/Relationsabfragen:

| Datei / Bereich | Grund |
| --- | --- |
| `MembersTeamMembershipLock`, `LockedMembersTeamMemberships` | Aktuelle, geordnete Sperrabfragen und unveränderliche Sicht während der Sperre. |
| `RpgAccess` | Kombination aus eindeutiger AG, aktiver Mitgliedschaft und tatsächlicher AG-Leitung; keine allgemeine Vereinsrollenprüfung. |
| `PollVotingService::assertMemberEligible` | Bisherige SQL-Auswahl zugelassener Rollen einschließlich des nicht teamgebundenen Legacy-Fallbacks. |
| `VeranstaltungsBaxxService::eligibleUserIds` | Mengenabfrage aller relevanten Teilnehmer; Vergabe wird unter den vorhandenen Mitgliedschaftssperren entschieden. |
| `Todo`-Benachrichtigungen, Newsletter, Kontakt-, Feedback- und Fehlermail-Empfänger | Mengenabfragen für Empfängerlisten. |
| `DashboardController`-Anwärterliste, Tourverwaltung, Mitglieder- und Teamlisten | Darstellung mehrerer Zuordnungen einschließlich vorhandener Pivot-Spalten. |
| `MaddraxikonStreakService` | Datenbankfilter über eine Menge von Teilnehmern. |
| `RepairLegacyRewardWallets` und historische Migrationen | Aggregierte Auswertung vorhandener Wallet-Zuordnungen bzw. unveränderte historische Datenmigration. |

Die Teambezüge und Rollenmengen werden nicht vereinheitlicht. Insbesondere bleibt
`hasVorstandRole()` einschließlich Kassenwart erhalten. Jetstream-Eigentümer
werden nicht zu Enum-Administratoren umgedeutet. Der Poll-Fallback prüft über
`Membership` tatsächlich `team_user`, obwohl sein bisheriger Kommentar eine
Legacy-Tabelle erwähnt. Diese fachlichen Auffälligkeiten bleiben getrennte
Entscheidungen.

## Nachweis

Die reproduzierbaren Rohdaten und Quellhashes stehen in
`measurements/refactoring-rollen-mitgliedschaften.json`.

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Gesamtsuite | 4.214 bestanden, 20.329 Assertions; 59 umgebungsabhängig übersprungen. |
| Kontext, Lifecycle, Teamresolver, Navigation und Architektur | 121 bestanden, 1.095 Assertions. |
| Abschließender Xdebug-Lauf | 50 bestanden, 363 Assertions. |
| Echte MariaDB-Prüfungen | Drei bestanden, 50 Assertions; mit `--fail-on-skipped`. |
| Chromium | Alle sieben Browserfälle bestanden, einschließlich Teamwechsel und Direktzugriff. |
| PHPStan für Kontext, Snapshot, Middleware und Rollenadapter | Keine Fehler. |
| Laravel Pint | Alle 19 geänderten PHP-Dateien bestehen die Formatprüfung. |

| Baustein | Abgedeckte / ausführbare Zeilen | Vollständig abgedeckte Methoden |
| --- | --- | --- |
| `MembershipContext` | 104 / 104 (100 %) | 17 / 17 |
| `MembershipSnapshot` | 1 / 1 (100 %) | 1 / 1 |
| `ResetMembershipContext` | 4 / 4 (100 %) | 1 / 1 |
| `UserRoleService` | 7 / 7 (100 %) | 1 / 1 |

Diese Coverage-Zahlen gelten für die genannten Anwendungsklassen im erfolgreichen
Xdebug-Fachlauf. Sie sind keine Aussage über die Gesamtprojekt-Coverage oder die
vollständige Zweigabdeckung. Die Quellhashes der gemessenen Klassen dokumentieren
den ursprünglichen Refactoringstand (`afdf95bc`). Nachfolgende Review-Korrekturen
werden unten gesondert ausgewiesen. Die bestehenden Coverage-Baselines und
CI-Prüfungen wurden nicht abgesenkt oder verändert.

### Datenbankzugriffe

Die Messungen verwenden PHP 8.5.12, SQLite im Arbeitsspeicher und den Array-Cache
im isolierten PHP-Testcontainer. Während der Vorher- und Nachhermessung liefen
keine weiteren Testläufe parallel. Angaben sind Mediane aus jeweils zehn
Messrunden nach einer Aufwärmrunde. Die Baseline ist der Anwendungscode aus
`ef37fd219de99fe69b01e10c80225577a2e88cb4` mit demselben zusätzlich eingebrachten
Messharness.

| Rolle / Szenario | SQL gesamt vorher → nachher | SQL mit `team_user` vorher → nachher | Laufzeit vorher → nachher, ms |
| --- | --- | --- | --- |
| Mitglied / Dashboard | 37 → 27 | 15 → 3 | 65,666 → 60,198 |
| Vorstand / Dashboard | 38 → 27 | 16 → 3 | 73,278 → 66,572 |
| Admin / Dashboard | 38 → 27 | 16 → 3 | 76,648 → 76,436 |
| Mitglied / öffentliche Seite | 14 → 10 | 9 → 3 | 38,506 → 31,561 |
| Vorstand / öffentliche Seite | 14 → 10 | 9 → 3 | 36,436 → 33,828 |
| Admin / öffentliche Seite | 14 → 10 | 9 → 3 | 32,705 → 37,804 |
| Mitglied / Navigation und Suche | 20 → 16 | 14 → 6 | 41,644 → 37,022 |
| Vorstand / Navigation und Suche | 23 → 17 | 16 → 6 | 41,839 → 38,660 |
| Admin / Navigation und Suche | 22 → 16 | 16 → 6 | 39,166 → 41,883 |
| Mitglied / Livewire | 8 → 10 | 4 → 4 | 28,873 → 27,509 |
| Vorstand / Livewire | 10 → 11 | 6 → 5 | 46,507 → 45,931 |
| Admin / Livewire | 10 → 11 | 6 → 5 | 50,270 → 50,668 |

Die Dashboard-Mitgliedschaftsabfragen sinken damit von 15–16 auf drei. Lokale
Laufzeiten schwanken und fallen nicht in jedem Szenario niedriger aus; ein
allgemeiner Produktions-Laufzeitgewinn wird daraus nicht abgeleitet.

Livewire-Updates laden Teamrelationen und Rollen bewusst frisch und benötigen
dadurch ein bis zwei zusätzliche Gesamtqueries. Der Mutationstest benötigt
weiterhin vier Queries für Mitglieder bzw. sechs für Vorstand und Admin. Dieser
Aufwand bewahrt die Aktualität maßgeblicher Schreibentscheidungen. Rohdaten
enthalten zusätzlich SQL-Zeit, Streuung und Spitzenspeicher je Szenario.

Die neuen Regressionen prüfen alle sieben Rollen, negative und ungültige Werte,
mehrere Modellinstanzen, getrennte Verbindungen, Eigentümer- und Tokenregeln,
Pivot-Mutationen, Teamwechsel, Namen und Eigentümer, verschachtelte Transaktionen,
Rollbacks und Wiederholungen sowie HTTP-, Livewire-, Job- und Kommando-Grenzen.
Tests zur Wiederverwendung laufen ohne die äußere `RefreshDatabase`-Transaktion.

MariaDB-Tests verwenden die bestehende isolierte Testkonfiguration. Ein
unabhängiger Prozess demotiert einen Benutzer nach einer frühen Rollenauflösung;
die anschließende gesperrte Entscheidung muss den aktuellen Anwärterstatus sehen.
Zusätzlich werden SQL-Collations direkt gegen das bisherige SQL-Prädikat verglichen.

Der ergänzte Chromium-Fall wechselt von einem Admin im Mitglieder-Team zu einer
AG mit Mitwirkendenrolle. Er prüft sowohl die Navigation als auch den verweigerten
Direktzugriff auf die Verwaltungsstatistik und den weiterhin erlaubten Zugriff
auf die ausdrücklich an das Mitglieder-Team gebundene Veranstaltungsverwaltung.

## Erste Code-Review-Runde

Der Befund zur expliziten frischen Rollenabfrage wurde bestätigt und behoben.
Zuvor leerte `freshForUserInTeam()` nur die Snapshot- und String-Caches. Bereits
geladene `teams`-Relationen konnten anschließend die alte Rolle wieder in den
Snapshot-Cache der Navigation eintragen. Das war auch nach dem Entzug einer
Adminrolle oder dem Löschen der Mitgliedschaft möglich.

Der frische Lesezugriff registriert weiterhin zuerst die übergebenen Modelle und
ruft danach `reset()` auf. Dadurch werden auch der Mitglieder-Team-Verweis und
die Relationen aller beobachteten Modellinstanzen verworfen, bevor die
Mitgliedschaft neu aus der Datenbank gelesen wird.

Der Regressionstest lädt zunächst die Navigation zweier Instanzen desselben
Benutzers, ändert die Pivot-Zeile per SQL ohne Modellereignisse und liest die
Mitgliedschaft ausdrücklich frisch. Anschließende Navigations-, Rollen- und
Bool-Prüfungen dürfen keine Adminrechte wiederherstellen. Die Varianten prüfen
eine Herabstufung zum Mitglied und das vollständige Entfernen der Zuordnung.
Der Test schlug auf dem ursprünglichen Code an der erneuten Navigation fehl.

Die vollständige Relationsinvalidierung erfordert innerhalb einer Transaktion
zusätzliche aktuelle Teamabfragen für die Autorisierung. Der bisherige
Navigationstest verwendet `RefreshDatabase` und begrenzt diesen Pfad deshalb
jetzt auf sechs Abfragen. Ein zusätzlicher Integrationstest ohne äußere
Testtransaktion erhält das bisherige Limit von höchstens drei Abfragen im
cachefähigen Lesekontext. Die gezielten Kontext-, Lifecycle-, Navigations- und
Rollenprüfungen bestehen mit 159 Tests und 1.298 Assertions.

Der erfolgreiche Xdebug-Lauf besteht mit 53 Tests und 392 Assertions.
`MembershipContext` erreicht nach der Korrektur 103/103 ausführbare Zeilen und
17/17 vollständig abgedeckte Methoden. Snapshot, Middleware und Rollenadapter
bleiben ebenfalls bei 100 % Zeilenabdeckung. Die drei geänderten PHP-Dateien
bestehen die Prüfung mit Laravel Pint.

Die abschließende PHP-Gesamtsuite besteht mit 4.217 Tests und 20.358 Assertions;
59 umgebungsabhängige Fälle werden übersprungen.

Die obenstehenden Benchmarkwerte dokumentieren weiterhin den ursprünglichen
Refactoringstand; sie wurden für diese Review-Korrektur nicht neu gemessen.

## Reproduktion

```powershell
# PHP-Gesamtsuite; vorhandener isolierter Docker-Testworkflow.
node tools/testing/run.mjs php

# Neue Fach- und Lifecycle-Regressionen.
docker compose -f docker-compose.tests.yml run --rm -T --no-deps php php vendor/bin/pest --filter='MembershipContext|UserRoleService|UserRoleTest|NavigationMenuTest' --compact

# Aktuelle Mitgliedschaften unter MariaDB-Sperren und SQL-Collation.
docker compose -f docker-compose.tests.yml up -d --wait db
docker compose -f docker-compose.tests.yml run --rm -T --no-deps php php vendor/bin/pest --configuration=phpunit.mariadb.xml --filter=MembersTeamMembershipMariaDbConcurrencyTest --fail-on-skipped --compact

# Browserrollen und tatsächlicher Teamwechsel.
docker compose -f docker-compose.tests.yml run --rm -T --no-deps browser node node_modules/playwright/cli.js test tests/e2e/ux-roles.spec.js tests/e2e/membership-context.spec.js --project=chromium --workers=1
```

`tests/Performance/MembershipContextBenchmark.php` führt pro Rolle und Szenario
eine Aufwärmrunde und zehn Messrunden aus. `MEMBERSHIP_BENCHMARK_OUTPUT` bezeichnet
die Ergebnisdatei innerhalb des jeweiligen Testcontainers. Die Vorhermessung
verwendet den tatsächlichen ursprünglichen Anwendungscode; die Nachhermessung
verwendet dieselben Fixtures und Testoperationen. Beide enthalten Dashboard,
öffentliche Seite, Navigation mit Suche, Livewire-Folgeaufruf und Rollenmutation.

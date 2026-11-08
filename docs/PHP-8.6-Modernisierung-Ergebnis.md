# PHP-8.6-Modernisierung: Änderungen und Prüfnachweise

Stand: 8. November 2026. Unterstützte Laufzeiten: PHP 8.5 und PHP 8.6;
die Produktion verwendet weiterhin PHP 8.5.

## Anwendung und Abhängigkeiten

Composer erlaubt regulär `~8.5.0 || ~8.6.0`. Laravel wurde von 13.39.0 auf
13.40.0 aktualisiert; die dazu aufgelösten stabilen Symfony-, URI- und
Termwind-Versionen stehen im Lockfile. Livewire 4.4.8 und maryUI 2.9.11 bleiben
auf ihrem geprüften stabilen Stand. Es gibt weder Vendor-Patches noch eine
simulierte PHP-Plattform oder ignorierte Plattformanforderungen.

- CSV-Importe legen den bisherigen Backslash-Dialekt ausdrücklich fest.
  Mitglieder- und Fantreffen-Exporte verwenden `escape: ''`; Tests prüfen
  Roundtrips mit Anführungszeichen, Backslashes, Umlauten, Zeilenumbrüchen und
  den bestehenden Schutz gegen Tabellenformeln.
- PDO verwendet unmittelbar die treiberspezifische Konstante. Überflüssige
  GD-Cleanup- und Reflection-`setAccessible()`-Aufrufe wurden entfernt.
- Beide Markdown-Renderer verwenden numerische Unicode-Entities anstelle des
  veralteten HTML-Entities-Modus von `mb_convert_encoding()`. Unicode und
  HTML-Bereinigung sind gemeinsam abgesichert.
- Die Freigabemail deklariert ihre Benutzereigenschaft. Fehlende oder falsch
  typisierte Login-Namen und leere Statistikmengen erzeugen keine
  Null-Argument-Deprecations mehr.
- Formularfelder bestimmen einen gültigen Validierungsschlüssel auch aus dem
  HTML-Namen; unbenannte Felder lesen keine fremden Fehlermeldungen. Die
  Textarea-Registrierung berücksichtigt auch den Konsolen-Testpfad.
- Die chronologische Beitragsauswertung verwendet einen ausdrücklichen
  Vergleich einschließlich Revisions-ID. Damit wird der weiterhin vorhandene
  `SORT_LOCALE_STRING`-Zweig in Laravels Mehrfachsortierung nicht ausgewertet.
  Ein Test mit vertauschten und zeitgleichen Beiträgen sichert die Belohnung ab.
- Maddraxikon-URLs weisen rohe NUL-Zeichen vor der Query-Verarbeitung zurück.
  Die Titelprüfung behält ihre ausdrückliche Behandlung von Form-Feed bei.

## Auditumfang

Geprüft wurden eigener PHP-Code, Konfiguration, Migrationen, Tests, Werkzeuge,
Einstiegspunkte sowie kompilierte Blade-Views. `composer test:compatibility`
kombiniert AST-Prüfungen mit Syntaxprüfungen durch den tatsächlichen Interpreter.
Ein leerer Dateibestand ist kein erfolgreicher Nachweis.

Der Abgleich mit den offiziellen [Deprecations](https://www.php.net/manual/en/migration86.deprecated.php)
ergab folgende Bewertung für den eigenen Code:

| Bereich | Ergebnis |
| --- | --- |
| Reservierte Namen, `finally`, Konstruktoren, `__debugInfo`, `define`, Typ-Aliase | Keine verbleibenden Fundstellen; AST- und Syntaxprüfung. |
| `is_a`/`is_subclass_of`, Objektargumente für Array-/Query-Funktionen | Aufrufkontexte geprüft; vorhandene Unterklassenprüfungen erlauben Klassennamen, Query-Aufrufe erhalten Arrays. |
| mbregex, Locale-Sortierung, Metaphone | Keine eigene mbregex-/Metaphone-Nutzung; Beitragsvergleich angepasst. |
| SPL, CSV-Methoden, Reflection | Keine eigene betroffene SPL-Nutzung; obsolete Reflection-Aufrufe entfernt, statische Eigenschaften korrekt angesprochen. |
| Sessions | Keine eigenen nativen Handler; Laravels geladener Array-Handler implementiert `create_sid()` und `validateId()`. |
| Bzip2, GMP, MySQLi, Zlib-Objektoptionen | Keine betroffene eigene Nutzung. |

Die [inkompatiblen Änderungen](https://www.php.net/manual/en/migration86.incompatible.php)
wurden zusätzlich auf relevante Eingaben geprüft: NUL und Form-Feed wurden in
den URL-Pfaden abgesichert. Eigener Code verwendet keine zustandsabhängige
`__isset()`-Materialisierung, betroffenen `unpack()`-Formate oder ungenauen
Zahlensystem-Konvertierungen. Array-Flags, Dateipfade und Erweiterungsaufrufe
werden ergänzend durch die bestehenden Funktions- und Integrationstests geprüft.
Die [weiteren Änderungen](https://www.php.net/manual/en/migration86.other-changes.php)
ergaben keinen zusätzlichen eigenen Migrationsbedarf. Ältere Deprecations und
Soft-Deprecations wurden ebenfalls gesucht; insbesondere CSV, GD, PDO,
HTML-Entities, dynamische Eigenschaften und Null-Argumente wurden modernisiert.

## Verbindliche Fehlererkennung und CI

Alle sechs PHPUnit-Konfigurationen nutzen `E_ALL`, `failOnDeprecation` und den
gemeinsamen Testbootstrap. Ein eigener Laravel-Kanal und ein natives PHP-Log
erfassen auch Meldungen, die Laravels Fehlerhandler übernimmt. Meldungen oder
fehlende Nachweisdateien lassen den Testprozess scheitern. Negative Fixtures
prüfen native, Compile-Time-, Laravel-, HTTP- und Abhängigkeitsmeldungen;
absichtlich veralteter Code läuft ausschließlich in isolierten Unterprozessen.

Der PHP-8.6-Koordinator verlangt alle acht Pflichtstufen und überprüft am Ende
unveränderte SHA-256-Werte für Manifest, Lockfile und Paketmetadaten. Er erweitert
keine PHP-Vorgabe mehr. Auch frühe Meldungen vor umfangreicher späterer Ausgabe,
erfolgreiche HTTP-Antworten und Prozesse mit Exit-Code 0 umgehen die Prüfung
nicht. Nicht verfügbare Laufzeiten, fehlende Diagnosen und übersprungene Stufen
blockieren den festen Check **PHP 8.6 erforderlich**. Das Deployment wartet
ebenfalls auf diesen Workflow; Zeitbudgets und Merge-Queue-Auslöser sind angepasst.

Im aktiven GitHub-Ruleset [Secure main, ID 4508779](https://github.com/McNamara84/omxfc-vereinswebseite/rules/4508779)
ist **PHP 8.6 erforderlich** seit dem 8. November 2026 als erforderlicher
Statuscheck der GitHub-Actions-App 15368 hinterlegt. Bestehende Regeln,
Branchbedingungen und Ausnahmen wurden unverändert erhalten und nach dem
API-Update erneut verglichen. Die bestehenden Ausnahmen können den Ruleset
weiterhin umgehen.

Die Tests benötigen keine privaten PDF-Dateien mehr. Fehlende lokale
Serien-Metadaten erhalten ausschließlich im Testbootstrap einen leeren
Ausgangsbestand; vorhandene Dateien werden nicht überschrieben. Testsprache und
optionale Streak-Aktivierung sind ausdrücklich festgelegt. Die fachlichen
Streak-Tests aktivieren das Feature weiterhin selbst.
Der Profil-Tour-Browserfixture enthält zusätzlich die zur Statistik gehörenden
Bewertungs- und Stimmenfelder. Damit bleibt der gemeinsam genutzte Testkatalog
auch bei einem frischen Checkout für nachfolgende Menüprüfungen gültig.

## Abschließende lokale Prüfungen

Die Linux-Testcontainer verwenden PHP 8.5.12 und tatsächlich PHP 8.6.0RC5,
Composer 2.11.1, Node 26.13.0 und MariaDB 13.0.2. RC5 wurde aus dem offiziellen
[Vorab-Quellarchiv](https://www.php.net/pre-release-builds.php) gebaut und gegen
SHA-256 `abd7e3a652cfec56a2904abef7e8f37206fa07a8c1505a5a8a9bfdd25a1f1ba9`
verifiziert. Datenbanken, Caches und private Testdateien liegen in isolierten
Containern; die Datenbanken sind flüchtig.

Der abschließende Integritätslauf bestätigt 201 unveränderte Paketmetadaten und
die bytegleichen Workspace-Dateien. SHA-256 des geprüften `composer.lock`:
`c217e3bd35dafcc00a7583680da44a1cde5c3924c91f6cb7606953d9429c388f`.

| Prüfung | PHP 8.5.12 | PHP 8.6.0RC5 |
| --- | --- | --- |
| Vollständige Unit-, Feature- und Integration-Suite | 4.589 bestanden, 23.825 Assertions | 4.589 bestanden, 23.832 Assertions |
| Maddraxikon-/Baxx-/Romantausch-MariaDB-Suite | 562 bestanden | 562 bestanden |
| RPG-MariaDB-Suite | 41 bestanden | 41 bestanden |
| Veranstaltungs-Baxx-MariaDB-Suite | 8 bestanden | 8 bestanden |
| Pest-Browser-Regression | 1 bestanden, 77 Assertions | 1 bestanden, 77 Assertions |
| PHPStan, Rector, Architektur, Profanity | Erfolgreich | Erfolgreich |
| PHP-/Syntaxaudit | 1.540 Dateien, 0 Befunde | 1.540 Dateien, 0 Befunde |
| Kompilierte Blade-Views | 371 Dateien, 0 Befunde | 371 Dateien, 0 Befunde |
| Chromium | 286 bestanden, 2 Skips | 286 bestanden, 2 Skips |
| Firefox | 286 bestanden in zwei CI-Shards, 2 Skips | 285 direkt bestanden, 1 nach Wiederholung bestanden, 2 Skips |

Die reguläre SQLite-Suite überspringt jeweils 68 MariaDB-Parallelzugriffstests.
Diese laufen in den drei ausdrücklich mit `--fail-on-skipped` ausgeführten
MariaDB-Konfigurationen; dort gibt es keine Skips. Der Gesamtlauf wurde mit
unverändert strenger Fehlerbehandlung, aber deaktiviertem vorzeitigem Abbruch
ausgeführt, damit sämtliche Fehler sichtbar würden.

Die zwei vorhandenen Browser-Skips betreffen einen deaktivierten
Kompendium-Uploadtest und den nur ausdrücklich angeforderten Screenshotexport.
Der Firefox-Fokusfall unter RC5 wurde nach dem vollständigen Lauf zusätzlich
dreimal ohne automatische Wiederholungen erfolgreich geprüft. Beide
Chromium-Gesamtläufe wurden nach der Korrektur der Profil-Tour-Daten vollständig
neu ausgeführt. Alle abgeschlossenen Browserphasen sind ohne PHP-Deprecations.
Der erste ungeteilte PHP-8.5-Firefox-Lauf traf beim Admin-Menü auf das
achtsekündige Navigationslimit für `/mitglieder`. Der vollständige Nachlauf in
der regulären CI-Aufteilung bestand anschließend mit 147 und 139 Tests sowie
je einem Skip; beide Testgruppen benötigten keine Wiederholung.

Zusätzlich erfolgreich: alle 110 Node-Tests unter Linux, alle 607 Vitest-Tests
in 42 Dateien, der Vite-Produktionsbuild, Composer-Validierung und
Plattformprüfungen auf beiden PHP-Reihen sowie Pint für alle 45 geänderten
PHP-Dateien. Nach der letzten Änderung am CI-Koordinator wurden dessen 26
Node-Regressionstests erneut vollständig bestanden. Composer meldet für das
finale Lockfile keine bekannten Sicherheitsadvisories. Actionlint validiert
alle drei geänderten Workflows.

Lokale Rohprotokolle, JUnit-Nachweise, Integritätsmetadaten und Browserarchive
liegen unter `storage/framework/testing/php86-final-evidence/`.

Ein gehosteter Lauf für diese Änderung setzt deren Push voraus. Der lokale
Commit allein startet GitHub Actions nicht. Vorhandene Coverage-, Mutations-
und Sicherheitsjobs bleiben erhalten; aus lokalen Läufen ohne Coverage-Treiber
wird keine prozentuale Codeabdeckung abgeleitet. Das stabile PHP-8.6-Release
bleibt durch den laufenden Pflichtworkflow erneut zu prüfen.
Der bestehende Ausschluss von `TypesenseInternalSnapshotTest.php` aus der
regulären Suite und die getrennte Nextcloud-Installation bleiben außerhalb
dieses Laufzeitnachweises.

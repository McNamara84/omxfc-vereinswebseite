# PHP-Tests mit Pest

Die PHP-Suite verwendet funktionale Pest-Tests. `test()` beschreibt einen
fachlichen Vertrag; passende Laravel-, Livewire-, HTTP- und Datenbank-Assertions
ergänzen `expect()`. Strikte Gleichheit bleibt mit `toBe()` erhalten.

## Aufbau und Fixtures

- `Unit`: ohne Laravel-Bootstrapping, außer wenn die Datei ihre Basis ausdrücklich
  anders festlegt. Reine Fachregeln benötigen keine Datenbank.
- `Integration` und `Feature`: jede Datei registriert die tatsächlich benötigte
  Testbasis und ihre Traits. Der manuelle Typesense-Test bleibt eigenständig.
- `Browser`: gemeinsame Laravel-/Datenbankbasis in `Pest.php`; Rollen- und
  Mitglieder-Fixtures aus `Concerns/CreatesUserWithRole.php`.
- `*TestFixtures.php`: dateibezogene Hilfsmethoden und typisierte Eigenschaften.
  Solche Dateien enthalten keine Tests und werden über PSR-4 geladen. Wiederholt
  benötigte fachliche Vorbereitung gehört in `Concerns/` oder `Support/`.
- Fünf abstrakte Fixture-Basen erhalten die besonderen Commit-/Cleanup-Lifecycles
  von Mitgliedschafts- und RPG-Parallelzugriffstests. Diese Abläufe dürfen nicht
  durch globales `RefreshDatabase` ersetzt werden.

`beforeEach()` und `afterEach()` begrenzen Vorbereitung und Cleanup auf die
betroffenen Fälle. Bestehende globale HTTP-Fakes, einmalige Seeds und die
Testdatenbank-Schutzprüfung aus `TestCase.php` bleiben verbindlich.

## Datasets und Assertions

Varianten derselben Regel werden als benannte Datasets ausgeführt. Eine Änderung
an der Anzahl der Testfunktionen darf keine Eingabe, Rolle, Grenze oder
Seiteneffektprüfung verlieren. Eigenständige Browser- und HTTP-Verträge bleiben
getrennt, auch wenn beide dieselbe Seite öffnen.

Datasets werden dateilokal definiert, solange sie nur dort benötigt werden.
Wiederverwendete Fachfälle können nach `Datasets/` verschoben werden. Objekte
entstehen über gebundene, typisierte Closures je Testfall; vorbereitete
Datenbankobjekte gehören nicht in beim Laden ausgeführte Provider.

Exceptions werden am auslösenden Aufruf mit `toThrow()` geprüft. Mehrteilige
Fehlerverträge behalten ihre Catch-Prüfungen, etwa für exakte Fehlermeldungen und
Cleanup. Native PHPUnit-Assertions bleiben für spezielle Comparator-Optionen
und Diagnosemeldungen zulässig.

`Support/PhpunitMetadata.php` überträgt die bestehenden Attribute
`WithoutErrorHandler`, `Large`, `CoversMethod` und `CoversNothing`, für die der
installierte Pest-Stack keine passende flüssige API bereitstellt. Die kleine
Factory-Anbindung wird durch `Unit/PestMetadataTest.php` gegen die tatsächlich
generierten PHPUnit-Klassen geprüft. Bei Frameworkupdates wird diese Anbindung
zusammen mit den Regressionstests überprüft.

## Verbindliche Prüfungen

`composer test` führt die vollständige reguläre Suite aus. Die drei MariaDB-Suiten
laufen gesondert mit `--fail-on-skipped`; SQLite-Skips ersetzen ihre Abnahme nicht.
Die Coverage-Grenzen in `coverage-baselines/` gelten insgesamt, je Datei und für
die bisher abgedeckten Stellen unveränderter Quellen. Vor einer strukturellen
Migration kommt ein frischer Vorher-Snapshot als zusätzliche Vergleichsbasis hinzu.
Für diese Migration wird er unverändert als
`coverage-baselines/php-pest-modernization-before.json` zusätzlich in CI geschützt.

`composer test:types`, `composer test:rector`, `composer test:arch` und
`composer test:profanity` prüfen die festgelegten Bereiche. PHPStan bleibt auf
Level 10. Neue Bereiche werden mit präzisen Typverträgen aufgenommen; die
Analyse wird nicht durch eine pauschale Fehlerbaseline ausgeweitet.

Das bisherige Mutation-Gate mit `composer test:mutate` bleibt bei 100 %.
`composer test:mutate:core` prüft zusätzlich Euro-Beträge und RPG-Kostenregeln,
ebenfalls mit 100 %. Bei Euro sind sechs äquivalente Änderungen gezielt und
begründet ausgenommen: zwei Änderungen der Split-/Padding-Länge,
zwei überflüssige Integer-Casts und zwei durch PHPs bisherige schwache
Skalarkonvertierung gleichwertige Normalisierungen. Diese Bewertung muss bei
Änderungen an Eingabegrammatik oder `strict_types` erneut erfolgen.

## Schnelle lokale Rückmeldung und Stabilitätsdiagnosen

`composer test:tia:fresh` zeichnet eine neue TIA-Basis auf; `composer test:tia`
führt anschließend betroffene Fälle aus. Unit, Feature und reguläre Integration
sind enthalten. MariaDB-Parallelzugriffe, Browser und das externe Typesense-Backend
behalten eigene Aufrufe. TIA ersetzt keinen vollständigen CI-/Coverage-Lauf.
Änderungen an gemeinsamen Fixtures, Seedern und Testwerkzeugen führen zu einer
breiteren Neuausführung.

Der Baseline-Workflow verwendet PCOV für den vollständigen Graphaufbau. Lokal
unterstützt Pest PCOV oder Xdebug; die Composer-Aufrufe aktivieren bei installiertem
Xdebug dessen Coverage-Modus. Der Workflow prüft den geschriebenen JSON-Graph vor
dem Upload. Binäre oder ungültige UTF-8-Eingaben erhalten benannte Dataset-Zeilen,
damit die Test-IDs als JSON gespeichert werden können; die Testeingaben bleiben
unverändert.

Die Composer-Aufrufe laden `tia-filter.php` über `auto_prepend_file` vor Composer
und Pest. Vendor- und Node-Abhängigkeiten werden schon beim Erfassen ausgelassen,
da Pest sie anschließend ohnehin aus dem TIA-Graph entfernt. Projektcode und
Fixtures bleiben enthalten. `tia-bootstrap.php` bietet einen Fallback für direkte
Pest-Aufrufe. Das reguläre Coverage-Profil verwendet weiterhin `bootstrap.php`.

`composer test:stability:repeat` startet den gewählten Umfang zweimal in getrennten
Prozessen und erhält den Fehlerstatus eines früheren Durchgangs.
`composer test:stability:retry` startet nach einem Fehlschlag ausschließlich die
im JUnit-Bericht fehlgeschlagenen Fälle erneut. Beide verwenden den gemeinsamen
Helfer `tools/testing/stability.php`; weitere Pest-Argumente werden weitergereicht.
Originale Fehlschläge bleiben sichtbar. Ein erfolgreicher Diagnoselauf ist keine
Freigabe für einen zuvor fehlgeschlagenen regulären Lauf.

## Nachvollziehbarkeit der Migration

`pest-migration-map.json` ordnet bisherige Klassenmethoden ihren Pest-Nachfolgern
zu. Dataset-Zusammenführungen behalten die Namen ihrer bisherigen Verträge.
`test-suite-inventory.json` beschreibt Runner, Bootstrapping, Datenbankbedarf und
Seeder; mit `npm run test:inventory` wird es neu erzeugt. Die tatsächlich
ausgeführten Dataset-Fälle und ihre Ergebnisse werden über JUnit geprüft.

Prüfergebnisse, Laufzeiten, Coverage-Vergleiche und die PHP-8.6-Abnahme stehen im
[Ergebnisbericht](../docs/Pest-Modernisierung-Ergebnis.md).

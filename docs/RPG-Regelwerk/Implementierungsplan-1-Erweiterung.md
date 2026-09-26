# Implementierungsplan: 1. Erweiterung für den Charakter-Editor

Stand: 26. September 2026

Status: Implementiert und geprüft.

Ziel: Erweiterung des Charakter-Editors unter `/rpg/char-editor` um die erste Regelerweiterung von Stefan Küppers.

## Grundlagen

- Lokale Arbeitsunterlage `docs/RPG-Regelwerk/Regelerweiterungen.pdf`: neue Rassen und Ausbildungen.
- Lokale Arbeitsunterlage `docs/RPG-Regelwerk/Regelwerk.pdf`, insbesondere Seiten 15–16 und 23–24: Charaktererschaffung, Kulturboni und Fertigkeitsregeln. Die Regelwerk-PDFs sind nicht Bestandteil dieses Repositorys.
- [Maddraxikon: Mars](https://de.maddraxikon.com/index.php?title=Mars): Hintergrund für die neue Kultur „Marsianische Städter“.
- [Maddraxikon: Morlock](https://de.maddraxikon.com/index.php?title=Morlock) und [Château d’Havré](https://de.maddraxikon.com/index.php?title=Ch%C3%A2teau_d%E2%80%99Havr%C3%A9): Hintergrund der Morlocks.
- Die verbindliche Zuordnung der Morlocks zu „Ruinenbewohner“ ist laut Holger mit Stefan Küppers abgestimmt. Im Editor wird dazu kein Hinweis auf eine ergänzende Auslegung angezeigt.

## Verbindliche fachliche Festlegungen

### Rassen und Kulturen

| Inhalt | Regeln und Zuordnung |
| --- | --- |
| Agarther | Übernehmen die Rassenwerte der Präkristofluu: Beruf +3, einen Pool von insgesamt 12 Fertigkeitspunkten und den Vorteil High-Tech-Ausrüstung. Im Pool ersetzt Nahkampf die Fertigkeit Feuerwaffen. Die Kulturboni entsprechen „Mensch des 21. Jahrhunderts“. |
| Marsianer | Übernehmen die Rassenwerte entsprechend der Erweiterung auf Grundlage der Präkristofluu. Im bestehenden 12-Punkte-Pool ersetzt eine wählbare andere Fertigkeit die Fertigkeit Feuerwaffen; dadurch entstehen keine zusätzlichen Punkte. Die geringere Robustheit bleibt eine Empfehlung und verursacht keinen automatischen Abzug. Verpflichtende Kultur: „Marsianische Städter“. |
| Morlock | IN −1; Heimlichkeit +1; Überleben +1; Vorteil Nachtsicht; Nachteil Lichtscheu. Verpflichtende Kultur: „Ruinenbewohner“. |

Die Agarther verwenden die Kulturboni „Mensch des 21. Jahrhunderts“: Beruf +1 sowie jeweils +1 auf zwei verschiedene Fertigkeiten aus Bildung, Pilot, Techniker und Wissenschaftler. Ein erklärender Kulturtext macht die Übernahme dieser Regelwerte verständlich.

Die neue Kultur **„Marsianische Städter“** ist der ersten Erweiterung zugeordnet und für Marsianer vorgesehen:

- Bildung +1.
- Techniker +1.
- Zusätzlich +1 auf genau eine Fertigkeit nach Wahl: Pilot, Wissenschaftler, Athletik oder Unterhalten.

Damit vergibt die Kultur insgesamt drei Bonuspunkte, entsprechend dem Umfang der Basiskulturen. Die Kulturboni werden zu den Rassenboni addiert. Bestehende Fertigkeitsgrenzen und Kombinationsregeln bleiben wirksam.

Beschreibung für den Editor:

> Die marsianischen Städter haben über Jahrhunderte eine eigenständige, technisch fortgeschrittene Gesellschaft entwickelt. Gemeinschaftliche Erziehung, praktische Berufsausbildung und das Zusammenleben in großen Familienverbänden prägen ihren Alltag. Neben ihrer Arbeit widmen sich viele Marsianer sportlichen oder künstlerischen Interessen.

### Ausbildungen

| Ausbildung | Kosten | Fertigkeiten |
| --- | --- | --- |
| Gladiator | 5 FP | Nahkampf, Unterhalten: Kämpfen, Intuition, Reiten |
| Priester | 5 FP | Kunde, Unterhalten: Predigen, Sprachen, Bildung |

Die Punkte werden entsprechend der bestehenden Ausbildungslogik verteilt. Die Schreibweisen „Unterhaltung“ und „Sprache“ aus der Erweiterung werden auf die vorhandenen Fertigkeitsnamen „Unterhalten“ und „Sprachen“ vereinheitlicht.

## Umsetzungsschritte

### 1. Regelquellen zentral verwalten

- [x] Einen gemeinsamen Katalog für Basisregelwerk und Erweiterungen mit stabilen Kennungen, Bezeichnung, Autor, Regelversion und Standardaktivierung einführen.
- [x] Rassen, Kulturen und Ausbildungen ihrer jeweiligen Regelquelle zuordnen.
- [x] Den Katalog so aufbauen, dass weitere Erweiterungen nach demselben Muster ergänzt werden können.
- [x] Gemeinsame Definitionen für Browserkonfiguration und serverseitige Prüfungen verwenden, um widersprüchliche Regeln zu vermeiden.

### 2. Regelberechnungen und Validierung ergänzen

- [x] Agarther, Marsianer und Morlock mit ihren Attributsmodifikatoren, Fertigkeitspools, Vor- und Nachteilen ergänzen.
- [x] Die festgelegten Kulturzuordnungen und die neue Kultur „Marsianische Städter“ integrieren.
- [x] Die Wahl der marsianischen Ersatzfertigkeit und des Kulturbonus im Browser und auf dem Server prüfen.
- [x] Gladiator und Priester einschließlich Kosten und Fertigkeitszuordnungen ergänzen.
- [x] Bestehende Regeln zu Fertigkeitsgrenzen, Ausbildungskosten und zur Kombination von Bildung und Intuition berücksichtigen.
- [x] Serverseitig verhindern, dass deaktivierte Erweiterungsinhalte durch manipulierte Formulare verwendet werden können.

Betroffene Bereiche:

- `app/Support/`: Regelquellen und gemeinsame Definitionen; insbesondere `RpgCharEditorTraining.php`.
- `app/Services/RpgCharacterCreationEvaluator.php`: Auswertung der Charaktererschaffung.
- `app/Services/RpgCharacterSheetService.php`: Regelkonfiguration, Validierung und Charakterdaten.
- `resources/js/alpine/char-editor.js`: Auswahlzustand, Berechnungen, Fertigkeitspools und Prüfungen im Browser.

### 3. Regelquellen im Editor auswählen und erkennen

- [x] Zu Beginn des Editors die Auswahl „1. Erweiterung von Stefan Küppers“ anbieten.
- [x] Die Erweiterung für neue Charaktere standardmäßig aktivieren; die Auswahl gilt pro Charakter.
- [x] Basisregelwerk und Erweiterung in den Auswahlmöglichkeiten und Informationsbereichen sichtbar unterscheiden.
- [x] Beim Deaktivieren die Erweiterungsinhalte aus der Auswahl entfernen.
- [x] Wenn bereits Erweiterungsinhalte verwendet werden, das Deaktivieren verhindern und die betroffenen Auswahlen konkret nennen.
- [x] Das Deaktivieren erst zulassen, nachdem die betreffenden Auswahlen gewechselt oder entfernt wurden. Dabei keine Eingaben automatisch löschen.
- [x] Den bestehenden Ablauf zum Ändern der Charakterdaten berücksichtigen, insbesondere die Sperrung der Basisdaten nach dem ersten Schritt.
- [x] Bedienbarkeit per Tastatur, mobile Darstellung und Dark Mode berücksichtigen.

Betroffene Ansicht: `resources/views/rpg/char-editor.blade.php`.

### 4. Auswahl speichern und PDF ergänzen

- [x] Aktivierte Regelquellen und deren Version im Charakterdatensatz speichern.
- [x] Eine ausdrücklich deaktivierte Erweiterung von einer fehlenden Angabe unterscheiden, damit sie nicht versehentlich wieder aktiviert wird.
- [x] Die Auswahl und die zugehörigen Eingaben nach Validierungsfehlern wiederherstellen.
- [x] Bereits gespeicherte Charaktere ohne Erweiterungsangaben dem Basisregelwerk zuordnen.
- [x] Einen kompakten Quellenhinweis auf dem PDF-Bogen ergänzen, beispielsweise „Basisregelwerk · 1. Erweiterung von Stefan Küppers“.
- [x] Beim späteren Export gespeicherter Charaktere deren hinterlegte Regelquellen verwenden.

Betroffene Bereiche: Charakter-Payload und Speicherung, `app/Services/RpgCharacterSheetPresenter.php` sowie `resources/views/rpg/char-sheet.blade.php`.

### 5. Funktion und Kompatibilität prüfen

- [x] PHPUnit-Tests für die neuen Rassen, Kulturboni, Fertigkeitspools und Ausbildungen ergänzen.
- [x] Serverseitige Ablehnung ungültiger Kulturzuordnungen und deaktivierter Erweiterungsinhalte testen.
- [x] Vitest-Tests für Standardaktivierung, Quellenfilterung, Wahlboni und Abschaltsperre ergänzen.
- [x] Sicherstellen, dass ein abgewiesener Abschaltversuch keine Charaktereingaben verändert.
- [x] Speicherung, Wiederherstellung nach Validierungsfehlern und PDF-Quellenhinweise testen.
- [x] Bestehende Basischaraktere und gespeicherte Charaktere ohne Erweiterungsangaben als Regression prüfen.
- [x] Den wesentlichen Bedienablauf mit Playwright prüfen, einschließlich Quellenkennzeichnung und Wechsel zwischen Basisregelwerk und aktivierter Erweiterung.
- [x] Die betroffenen PHP- und JavaScript-Tests, Formatprüfungen und den Frontend-Build ausführen.

Vorhandene Testbereiche: `tests/Unit/RpgCharEditor*`, `tests/Unit/RpgCharacter*`, `tests/Feature/RpgCharEditor*`, `tests/Feature/RpgCharacter*`, `tests/Vitest/char-editor.test.js` und `tests/e2e/char-editor.spec.js`.

### 6. Dokumentation aktualisieren

- [x] In der README die Erweiterungsauswahl und die ergänzten Inhalte beschreiben.
- [x] Dokumentieren, wie weitere Regelquellen und deren Inhalte ergänzt werden.
- [x] Die hier festgelegten Kulturzuordnungen und die neue marsianische Kultur als Grundlage für die Anpassung der Erweiterungs-PDF festhalten.

## Testabdeckung und technische Hinweise

Prüfergebnis der ersten Implementierung vom 26. September 2026:

| Prüfung | Ergebnis |
| --- | --- |
| PHP: `php artisan test --filter Rpg` | 200 Tests, 1.373 Assertions erfolgreich |
| JavaScript: `npm run test:vitest -- tests/Vitest/char-editor.test.js` mit V8-Coverage | 170 Tests erfolgreich; 89,79 % Zeilen-, 85,68 % Anweisungs- und 72,05 % Zweigabdeckung der gesamten `char-editor.js` |
| Playwright: `tests/e2e/char-editor.spec.js` | 42 Fälle in Chromium erfolgreich geprüft; die sechs Erweiterungsfälle zusätzlich in Firefox erfolgreich |
| Mobile Bedienung und Accessibility | Quellenwahl per Tastatur bei 390 px im Dark Mode geprüft; keine axe-Verstöße im geprüften Regelquellenbereich |
| Laravel Pint | Alle zwölf betroffenen PHP-Dateien bestehen die Formatprüfung |
| Vite-Build und `git diff --check` | Erfolgreich |
| PDF-Sichtprüfung | Dompdf erzeugt einen einseitigen Bogen mit lesbarer Kulturbezeichnung und freistehendem Quellenhinweis |

Die Abdeckungswerte beziehen sich auf das gesamte bestehende JavaScript-Modul,
nicht allein auf den hinzugefügten Code. PHP-Coverage wurde mangels installiertem
Coverage-Treiber nicht prozentual gemessen. Die Prüfungen wurden gezielt für den
RPG-Bereich ausgeführt.

Die Erweiterung verwendet `RpgCharEditorRuleCatalog` als gemeinsamen Katalog für
Regelquellen, Rassen und Kulturzuordnungen. Die aktive Auswahl wird im bestehenden
Charakter-Payload unter `rules.sources` gespeichert; zusätzliche Datenbankspalten
sind nicht erforderlich. `rule_choices` hält die Wahlboni und Punkteverteilungen
fest. Fehlende Quellenangaben werden als Basisregelwerk behandelt.

Abgedeckte Fälle:

- Alle drei Rassen mit Pflichtkulturen, additiven Boni sowie Vor- und Nachteilen.
- Alle vier marsianischen Kulturboni, unterschiedliche Ersatzfertigkeiten und
  Überschneidungen mit vorhandenen Poolfertigkeiten beziehungsweise Beruf +3.
- Genau zwölf Rassenpunkte; Ablehnung fehlender, fremder, negativer, gebrochener
  oder überhöhter Werte. Keine automatische Robustheitsreduktion.
- Bildung und Intuition gemeinsam nur mit „Kind zweier Welten“.
- Fünf FP je neuer Ausbildung sowie korrekte Spezialisierungen und Zuteilungen.
- Standardaktivierung, Quellenfilter, Abschaltsperre ohne Eingabeverlust und
  serverseitige Ablehnung deaktivierter oder unbekannter Quellen.
- Erhalt bezahlter Fertigkeitspunkte beim Wechsel marsianischer Wahlboni.
- Wiederherstellung nach Validierungsfehlern, Speicherung der Quellenversionen,
  Quellenhinweise im PDF und Kompatibilität mit älteren Charakteren.
- Echte Browserabläufe einschließlich Speicherung aller neuen Rassen sowie
  mobiler Tastaturbedienung und Barrierefreiheitsprüfung im Dark Mode.

Die JavaScript-Testdaten lassen sich mit
`php tests/Fixtures/update-rpg-extension-rules.php` aus dem Katalog erzeugen. Ein
PHP-Test prüft ihre Übereinstimmung mit der Browserkonfiguration. Die README
beschreibt das Ergänzen weiterer Quellen.

## Korrekturen aus Review-Runde 1

Abschließendes Prüfergebnis: 205 PHP-Tests mit 1.459 Assertions, 171
JavaScript-Tests sowie der neue Browserfall in Chromium und Firefox erfolgreich.
Laravel Pint, Vite-Build und die Diff-Prüfung sind ebenfalls erfolgreich.

- Der PDF-Bereich für Ausrüstung hat wieder ausreichend Platz. Ausrüstung,
  Munition, Notizen und situationsabhängige Kampfinformationen wachsen mit ihrem
  Inhalt; der Quellenhinweis folgt im normalen Seitenfluss. Die übrige Aufteilung
  ist entsprechend angepasst. Dompdf-Regressionstests prüfen tatsächlich
  gerenderte Textpositionen auf Abschneiden und Überlappungen, mit und ohne
  Porträt sowie mit maximalen Textlängen.
- Die marsianische Ersatzfertigkeit muss eine der Grundfertigkeiten aus der
  Browserauswahl sein. Feuerwaffen und Spezialisierungen wie „Beruf: Soldat“
  werden serverseitig abgelehnt, auch bei passenden manipulierten Poolwerten.
- Bei jedem tatsächlichen Rassenwechsel wird der letzte Stand neu gespeichert.
  Tests prüfen drei aufeinanderfolgende Wechsel mit jeweils geänderter
  Ersatzfertigkeit und Punkteverteilung.
- Dieser Plan ist für Git vorgemerkt, damit der README-Link auch im Repository
  verfügbar ist. Die lokal vorhandenen Regelwerk-PDFs werden als Arbeitsunterlagen
  benannt, ohne Links auf nicht veröffentlichte Dateien.

Ergänzte Regressionstests:

- `RpgCharacterSheetLayoutTest`: vier Varianten mit tatsächlich gerenderten
  PDF-Bereichen; kurze und maximale Exporttexte, jeweils mit und ohne Porträt.
- `RpgExpansionTest`: Spezialisierungen als Ersatzfertigkeit werden trotz passend
  manipulierter Rassenpunkte abgewiesen.
- Vitest und Playwright: drei Rassenwechsel mit jeweils aktualisierter Auswahl;
  der Browserfall wurde in Chromium und Firefox erfolgreich geprüft.

## Nachricht für Stefan

Entwurf zum Versenden nach der Umsetzung:

> Hallo Stefan,
>
> wir haben deine erste Erweiterung in den Charakter-Editor aufgenommen und dabei folgende Kulturzuordnungen ergänzt:
>
> Morlocks erhalten wie abgesprochen verbindlich „Ruinenbewohner“. Agarther übernehmen die Kulturboni „Mensch des 21. Jahrhunderts“.
>
> Für Marsianer haben wir die eigenständige Kultur „Marsianische Städter“ ergänzt: Bildung +1, Techniker +1 und zusätzlich Pilot, Wissenschaftler, Athletik oder Unterhalten +1 nach Wahl. Sie bildet die technisch geprägte Gesellschaft sowie ihre wissenschaftlichen, sportlichen und künstlerischen Interessen ab und bleibt mit insgesamt drei Bonuspunkten im Rahmen der Basiskulturen.
>
> Die marsianische Ersatzfertigkeit für Feuerwaffen gehört zum bestehenden 12-Punkte-Pool; zusätzliche Punkte entstehen dadurch nicht. Die geringere Robustheit bleibt eine Empfehlung. Bei den Ausbildungen haben wir die Fertigkeitsnamen „Unterhaltung“ und „Sprache“ an „Unterhalten“ und „Sprachen“ aus dem Basisregelwerk angepasst.
>
> Die Erweiterung ist standardmäßig aktiviert, kann abgeschaltet werden und wird im Editor sowie auf dem Charakterbogen ausdrücklich als „1. Erweiterung von Stefan Küppers“ gekennzeichnet.
>
> Viele Grüße
>
> Holger

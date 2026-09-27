# Umsetzung der Übungskämpfe

Stand: 27.09.2026. Die Implementierung liegt im Arbeitsverzeichnis; eine Produktivmigration oder Veröffentlichung wurde nicht ausgeführt. Grundlage ist der [abgestimmte Implementierungsplan](Implementierungsplan-Uebungskampf.md).

## Bedienung und Umfang

Unter `/rpg/uebungskaempfe` können aktive Mitglieder der AG Rollenspiel mit gespeicherten Charakteren Herausforderungen erstellen und annehmen. Der Einstieg ist außerdem bei „Meine Charaktere“ und im Dashboard verlinkt. Beide Seiten stimmen der Startentfernung und den sichtbaren Arena-Bedingungen zu. Herausforderungen verfallen nach sieben Tagen; erst die Annahme belegt die Charaktere. Pro Charakter ist höchstens ein laufender Kampf möglich.

Die eigene Kampfseite führt durch Ausrüstung, Initiative, Bewegung, Nah- und Fernkampf, Verteidigung, Schaden, Sondermanöver und psychische Talente. Das private Protokoll enthält Würfel, Modifikatoren, Entscheidungen, Regelstellen und automatische Schritte. Gleichzeitige Ansagen werden erst gemeinsam aufgedeckt. Aktualisierungen erhalten ungesendete Eingaben und den Tastaturfokus. Alte Protokollabschnitte sind über Pagination erreichbar.

Jede neue Entscheidung erhält 24 Stunden. Ein überfälliger HTTP-Aufruf verwendet ebenfalls die automatische Entscheidung, auch wenn der Scheduler noch nicht gelaufen ist. Zeitstempel werden in UTC gespeichert; die Anzeige verwendet deutsche Ortszeit. Regelfragen pausieren übrige Entscheidungen unter Erhaltung ihrer Restfrist. Ein nachgeholter Schedulerlauf überspringt keine neu entstehenden Fristen.

Kampfseite und vollständige Daten sind nur für Beteiligte und die aktuelle AG-Leitung zugänglich. Regelfragen darf nur eine unbeteiligte Leitung entscheiden. Leitungswechsel ändern die Zuständigkeit, ohne die Frist zu verlängern. Nach 24 Stunden gelten die veröffentlichten Standardauslegungen. Bei einem nicht darstellbaren Fall kann die unbeteiligte Leitung mit Begründung neutral abbrechen.

Das Dashboard zeigt Vereinsmitgliedern Herausforderung, Annahme und Ergebnis. Persönliche Hinweise nennen eigene Entscheidungen und ihre Fristen; die Leitung erhält zusätzlich offene Regelfälle. E-Mails werden für Einladung, Start und neue eigene Entscheidungen dauerhaft vorgemerkt, über die Queue verschickt und vor dem Versand auf Aktualität und Berechtigung geprüft.

Verwendet werden eingefrorene Charakterkopien mit Revision und Prüfsumme. Das Duell verändert weder den Originalcharakter noch EP, Baxx oder Inventar. Austritt, Charakterlöschung und Kontolöschung beenden betroffene Kämpfe und geben die Charakterbelegungen frei. Öffentliche Namen gelöschter Mitglieder werden anonymisiert.

## Regelbezug und Tests

Regelversion: `maddrax-2007-duel-v1`. Ausrüstungskatalog: `maddrax-2007-equipment-v2`. Die Prüfsumme des verwendeten Grundregelwerks ist in `RpgCombatRules::RULEBOOK_SHA256` festgehalten. Die Seitenangaben beziehen sich auf die gedruckten Seiten der lokalen PDF. Ergänzend zum Kampfkapitel sind allgemeine Proben, Eigenschaften, Ausrüstung, psychische Kräfte und die FAQ berücksichtigt.

| Regelgruppe | Quelle | Implementierung und Prüffälle |
| --- | --- | --- |
| Würfel, Erfolgs-/Patzerbedingungen und Widerstandsproben | S. 11–12, 47–48 | `CombatMath`; feste Würfelfolgen, natürliche 2/12, Gleichstände und genau einmal angewandte kritische Boni |
| Initiative, feste Reihenfolge und Gleichzeitigkeit | S. 46 | `CombatEngine`; verdeckte Ansagen, gleichzeitige Bewegung, erneute Initiative und gleichzeitige Bewusstlosigkeit |
| Nahkampf, Fernkampf, Reichweite und Schadensformel | S. 41, 47 | `CombatStats`, `CombatMath`, `ResolvesCombatAttacks`; RI-/MR-Grenzen, passende Attribute, Verletzungen, Schutz und kritischer Schaden |
| Verwundungen und Bewusstlosigkeit | S. 47–48 | Schadensgrenzen 0/1/2/3/4/5/6/7, Beispiel zur Wundakkumulation und beide veröffentlichten Auslegungen mittlerer Wunden |
| Parade, Ausweichen, volle Verteidigung und Patzer | S. 48–49 | Paradeverbrauch, wiederholtes Ausweichen, Angriffsverzicht, Bodenlage, Waffenverlust, Ladehemmung und Sehnenbruch |
| Entwaffnen, Niederwerfen, gezielter Schlag, zwei Waffen | S. 49–50 | Widerstandsproben ohne erneute Angriffswürfe, ST-Folgeprobe ohne normale Wunde, Bonusgrenzen, zwei getrennte Reaktionen |
| Feuerarten und Verbrauch | S. 41, 49 | E/H/S/A, Munitionsgrenzen, einzelne Zielauflösung statt mehrfacher Treffer, Nachladen und unverschossene Munition beim Patzer |
| Bewegung, Handbelegung und Mindestbildung | S. 40–41, 46, 49 | Bewegung in Zentimetern, Rennen, Wechseln/Aufheben als Aktion, Benutzungsproben für technische Waffen und Rüstung |
| Eigenschaften und Erweiterungscharaktere | S. 25–28 und aktivierte Editorquellen | `CombatTraits`, `CombatSnapshotFactory`; jede Editor-Eigenschaft erhält eine Erklärung, Quellenvalidierung, keine doppelte Anwendung gespeicherter Rassenboni |
| PEP und Parameter | S. 36–37, 65 | `CombatMath`, vorhandener `RpgCharacterPsychicCalculator`; Parametergrenzen, Kosten auch bei Abwehr und FAQ-Beispiel zur Pyrokinese |
| Beherrschung und Gedankenschild | S. 37 | Zuständigkeit für Handlungen, Ablauf in Spielzeit, erneuter Widerstand und passiver/geteilter Schild |
| Pyrokinese und Telekinese | S. 37, 50, 65 | Brennrunden, begrenzter Schadensbonus, Geschoss mit `[S] − 3`, Masseentscheid und verzögerte Bewegung |
| Empathie und Telepathie | S. 37 | Informationswirkung, Widerstand bei starken Gedankenbildern/Lesen, Mindestintelligenz, keine erfundenen Angriffsboni |
| Objekte, Driller, Kettensäge, Energiegewehr, Heilung | S. 25, 41, 43, 50 | Materialauslegung, separater Objektschaden, Driller-Nebenwirkung, Treibstoff, Regeneration in Spielzeit und Heilgel |
| Fristen, Zugriffsrechte und Datenintegrität | Simulatoranforderungen | HTTP-, Deadline- und Integrity-Tests einschließlich Sommer-/Winterzeit, gefälschter Eingaben, Snapshots, Löschung und öffentlichem Feed |
| Gleichzeitige Datenbankzugriffe | Simulatoranforderungen | Separate MariaDB-Prozesse: doppelte Aktion, konkurrierende Annahmen, Timeout gegen Spieleraktion und Löschung während einer Aktion |

Die konkreten Tests stehen in `tests/Unit/RpgCombatMathTest.php`, `tests/Unit/RpgCombatEngineTest.php`, `tests/Feature/RpgCombat*Test.php`, `tests/Vitest/rpg-combats.test.js` und `tests/e2e/rpg-combats.spec.js`. Der separate PHPUnit-Einstieg ist `phpunit.rpg-combat.xml`.

Die Waffentabelle auf Seite 41 korrigiert auch bestehende Editordaten: geworfener Stein P −2/S 0, Schleuder P −1/S 0, Bogen P −1/S +1. Die bisherigen Tests wurden entsprechend berichtigt.

## Ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| Neue PHP-Suite `phpunit.rpg-combat.xml` | 140 Tests, 899 Assertions bestanden |
| MariaDB-Parallelität `RpgCombatMariaDbConcurrencyTest` | 4 Tests, 39 Assertions bestanden |
| Bestehende RPG-, Dashboard- und Mitgliedschaftsprüfungen | 261 Tests, 1.892 Assertions bestanden |
| Vitest: neue Kampfsteuerung und vorhandenes Proben-Polling | 30 Tests bestanden |
| Playwright/Chromium | 4 Browserabläufe bestanden: vollständiger Nahkampf; Timeout und fremder Zugriff; mobile Pyrokinese mit Leitungswechsel; verdeckte gleichzeitige Ansagen |
| Barrierefreiheit und mobile Darstellung | axe auf der Kampfseite ohne Befund; mobile Ansicht mit 390 px ohne horizontalen Seitenüberlauf |
| Frontend-Build | `npm run build` erfolgreich; bestehender Hinweis auf großes Three.js-Bundle |
| Formatierung | Laravel Pint auf allen geänderten/neuen PHP-Dateien; `git diff --check` ohne Befund |

PCOV misst **94,49 % Zeilenabdeckung (1.442/1.526)** für die in `phpunit.rpg-combat.xml` aufgeführten neuen PHP-Klassen. Die JS-Messung für `resources/js/rpg-combats.js` ergibt **100 % Zeilenabdeckung und 91,66 % Zweigabdeckung**. Diese Werte gelten für den neuen Funktionsbereich, nicht für das Gesamtprojekt; Zeilenabdeckung beweist keine vollständige Regelkorrektheit. Die PHP-Regeltests verwenden festgelegte Würfelfolgen und unabhängig berechnete Erwartungen. Der Browser erzeugt Würfe über den echten Server; gezielte Testdaten sichern Initiative und Erfolg ohne zufallsabhängige Erwartungen.

Die 261 Regressionstests sind eine Auswahl der bestehenden RPG-, Dashboard- und Mitgliedschaftssuiten. Die gesamte Testsuite des Vereinsprojekts wurde nicht ausgeführt. Die Browserprüfung lief in Chromium; weitere Browser waren nicht Bestandteil dieses Nachweises.

## Auslegungen und Grenzen

`app/Support/RpgCombatRules.php` enthält die offenen Regelfälle SL-01 bis SL-17 einschließlich Unterfällen, Quellen, erlaubten Optionen und Timeout-Standard. Die Kampfseite zeigt diese Auslegungen. Dazu gehören unter anderem Initiativegleichstand, mittlere Wunden neben schweren Wunden, natürliche Waffen, Bodenlage, Nachladen, Handbelegung, psychischer Widerstand, Pyrokinese-Rundung, Telekinese, Driller und Objektschaden.

Die Arena ist offen und eben, mit diffusem Tageslicht und normaler Versorgung. Begleiter, Fahrzeuge, Reittiere, Gruppen, Deckung und freie erzählerische Szenen gehören nicht zur Simulation. Erzählerische Eigenschaften werden sichtbar eingeordnet und über begrenzte Leitungsentscheidungen behandelt. Gespeicherte Attributwerte gelten bereits einschließlich der im Editor angewandten Boni.

Eine Runde dauert drei Spielsekunden. Reale Wartezeit heilt keine Wunden und verlängert keine Talentwirkung. Das Standardlimit beträgt 100 Runden. Ein vollständig asynchroner Kampf kann deshalb länger dauern; das Limit und die Fristen sorgen für einen endlichen Ablauf. Automatische Entscheidungen wählen gültige einfache Handlungen, keine optimierte Taktik und keine kreativen psychischen Anwendungen. Ein Ergebnis ist eine Simulation unter diesen Bedingungen, keine Aussage über den Wert eines Charakters in jeder Spielrunde.

Mail-Aufträge und Kampfaktionen sind getrennt. Ein Absturz nach Annahme durch SMTP, aber vor Speicherung der Versandbestätigung kann trotz stabiler Message-ID eine doppelte E-Mail verursachen. Er erzeugt keinen zusätzlichen Wurf oder Dashboard-Meilenstein.

## Inbetriebnahme

1. Neue Dateien und Migration übernehmen; `php artisan migrate` ausführen.
2. Frontend mit `npm run build` erstellen.
3. Den bestehenden Laravel-Scheduler und Queue-Worker betreiben. `rpg:process-combats` ist minütlich in `routes/console.php` registriert und gegen überlappende Läufe geschützt.
4. Einstellungen in `config/rpg-combat.php` prüfen: aktiviert, 24 Stunden pro Entscheidung, sieben Tage Einladungsfrist, 100 Runden, Startentfernung und Verarbeitungsmenge.

Die Prüfungen verwenden SQLite im Speicher, die isolierte Datenbank `omxfc_rpg_test` beziehungsweise `database/playwright.sqlite`. Eine vorhandene Anwendungsdatenbank wurde für die Implementierung nicht migriert.

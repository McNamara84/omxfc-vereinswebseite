# Implementierungsplan: Abhängigkeiten und PHP-8.6-Kompatibilität

Stand: 1. November 2026. Dieser Plan ist umgesetzt; den tatsächlichen Versionsstand, Prüfnachweise und verbleibende Grenzen dokumentiert der [Umsetzungsbericht](Abhaengigkeiten-PHP-8.6-Ergebnis.md).

## 1. Vereinbarter Umfang

| Thema | Entscheidung |
| --- | --- |
| Abhängigkeiten | Neueste stabile Versionen einschließlich erforderlicher Major-Upgrades und Codeanpassungen; direkte und transitive Abhängigkeiten berücksichtigen. |
| Nextcloud | Vollständig ausgenommen. `nextcloud/` einschließlich Compose-Datei, Images, Konfiguration und Dokumentation bleibt unverändert. Keine Nextcloud-Container starten oder migrieren. |
| Produktives PHP | PHP 8.5 mit aktuellem Sicherheitsstand beibehalten. PHP 8.6 erhält einen zusätzlichen Kompatibilitätscheck. |
| Testumfang PHP 8.6 | Festgeschriebene Composer-Abhängigkeiten, Anwendungsstart, vollständige bestehende PHP-Tests, vorhandene MariaDB-Tests und Browser-Tests. |
| Reihenfolge | Prüfungen bauen aufeinander auf. Scheitert eine Voraussetzung, werden davon abhängige, insbesondere teure Stufen nicht gestartet. |
| Anzeige | Ergebnis im Namen eines eigenen Checks und in der Workflow-Zusammenfassung. Kein zusätzliches README-Badge. |
| Ausführung | Pull Requests gegen `main`, Pushes auf `main`, täglich auf dem Standardbranch und manuell. |
| CI-Ergebnis | Festgestellte PHP-8.6-Inkompatibilität bleibt ein erfolgreicher, grüner Check mit ausdrücklich negativem Kompatibilitätsstatus. Bestehende verpflichtende Prüfungen behalten ihre Fehlerwirkung. |

## 2. Ausgangslage im Repository

- `composer.json` verlangt derzeit `php: ~8.5.0` sowie unter anderem `ext-uri` und `ext-zip`. Die eigene PHP-Vorgabe verhindert bereits eine unveränderte Installation unter PHP 8.6.
- Der Backend-Stack verwendet Laravel 13, Livewire 4, Pest 5 und PHPUnit 13. PHP-8.5-Tests und Qualitätsprüfungen stehen in `.github/workflows/phpunit.yml`.
- Die MariaDB-Prüfungen verwenden `phpunit.mariadb.xml`, `phpunit.rpg-mariadb.xml` und `phpunit.veranstaltungs-baxx-mariadb.xml`.
- Browser-Prüfungen bestehen aus der Pest-Browser-Regression und Playwright für Chromium und Firefox. WebKit ist in der bestehenden Linux-CI deaktiviert und wird für diesen Check ebenfalls nicht ergänzt.
- Node.js und npm sind an mehreren Stellen festgelegt: `.node-version`, `.nvmrc`, `package.json`, Dockerfiles, Workflows und die zusammengesetzte Action `build-assets`.
- `docker/patch-npm-security.sh`, das zusätzliche npm-Lockfile und ein npm-Override enthalten gezielte Sicherheitskorrekturen. Ihre weitere Notwendigkeit muss mit den neuen Versionen geprüft werden.
- GitHub Actions und wesentliche Images sind bereits mit Commit-SHAs beziehungsweise Digests fixiert. Diese Absicherung bleibt erhalten.
- Dependency Review, Composer-/npm-Audits, Image-Scans und Prüfungen für Deployment und Datenbank-Upgrades sind vorhanden und werden weiterverwendet.
- `.github/scripts/wait-for-release-checks.mjs` wartet auf eine feste Liste verpflichtender Release-Workflows. Der zusätzliche experimentelle Check wird dieser Liste nicht hinzugefügt.

PHP 8.6 ist laut [offiziellem PHP-Zeitplan](https://wiki.php.net/todo/php86) für den 19. November 2026 vorgesehen. Der Check muss daher zunächst Vorabversionen und später stabile Builds derselben PHP-Reihe verarbeiten können.

## 3. Abhängigkeiten zuerst aktualisieren

### 3.1 Bestandsaufnahme und Zielversionen

1. Alle Versionsvorgaben außerhalb von `nextcloud/` erfassen: Composer, npm, zusätzliche Lockfiles, Dockerfiles, Compose-Dateien, Actions, Composer-Tooling, Scanner und sonstige heruntergeladene Werkzeuge oder extern eingebundene Bibliotheken.
2. Installierte beziehungsweise gesperrte Versionen, neueste stabile Upstream-Versionen, Sicherheitsmeldungen und notwendige Migrationsschritte vergleichen. Dafür Paketregistries, offizielle Release Notes und offizielle Image-Registries verwenden.
3. Für jeden Bestandteil Quelle, Zielversion und gegebenenfalls notwendige Anpassungen festhalten. Konkrete Zielversionen unmittelbar vor der Umsetzung ermitteln, damit dieser Plan keine inzwischen veralteten Versionsnummern vorgibt.
4. Für Images sowohl den aktuellen Release-Tag als auch den zugehörigen Manifest-Digest prüfen. Für Actions vollständige Commit-SHAs der ausgewählten offiziellen Releases ermitteln und Versionskommentare aktualisieren.
5. Wo die neueste stabile Einzelversion noch nicht mit dem Gesamtprojekt zusammenarbeitet, den Blocker und die aktuell sicherste kompatible Version ausdrücklich dokumentieren. Keine instabile Version oder ignorierte Sicherheitsprüfung als vollständiges Update ausweisen.

### 3.2 Composer und PHP-Werkzeuge

1. Direkte Anforderungen bei Bedarf über Major-Grenzen anheben; anschließend den gesamten Abhängigkeitsgraphen unter echtem PHP 8.5 aktualisieren.
2. Framework-, Livewire-/UI- und Testwerkzeug-Upgrades als zusammengehörige Gruppen bearbeiten. Notwendige Änderungen an Anwendung, Konfiguration und Testaufrufen anhand der jeweiligen Upgrade-Anleitungen umsetzen.
3. `composer.json` und `composer.lock` zusammen aktualisieren. Die vorhandenen Richtlinien gegen verwundbare, aufgegebene oder schädliche Pakete beibehalten.
4. Composer selbst sowie seine Prüfsummen und Image-Referenzen in CI und Docker konsistent aktualisieren.
5. Lockfile validieren, vollständige Plattformanforderungen prüfen und `composer audit --locked --abandoned=fail` ausführen. PHP-Erweiterungen einschließlich `uri`, Datenbanktreibern und Bildverarbeitung berücksichtigen.

### 3.3 npm, Node.js und Frontend

1. Projektabhängigkeiten und transitive npm-Abhängigkeiten aktualisieren; notwendige Major-Migrationen im Build, Frontend und Testwerkzeug umsetzen.
2. Node.js und npm auf aktuelle stabile, für den aktualisierten Stack geeignete Versionen bringen und alle Versionsvorgaben synchronisieren. Eine erforderliche Node-Major-Migration umfasst auch die `engines`-Vorgabe.
3. Das zusätzliche Manifest und Lockfile unter `docker/npm-security/` berücksichtigen. Die bisherigen npm-Patches nur entfernen, wenn die neuen Upstream-Versionen die betreffenden Probleme nachweislich beheben; sonst kompatible Korrekturen aktualisieren.
4. Den Override für `postcss-selector-parser` erneut begründen oder nach überprüfter Upstream-Behebung entfernen.
5. Die strikte Installationsskript-Policy beibehalten. Erforderliche neue Freigaben einzeln prüfen; keine pauschale Freigabe von Installationsskripten.
6. Reproduzierbarkeit mit `npm ci`, Audit und dem vorhandenen Skript-Inventar prüfen; Assets, Vitest und relevante Node-Prüfungen ausführen.

### 3.4 Images und GitHub Actions

1. Alle verwendeten Actions einschließlich lokaler Composite-Actions prüfen und externe Referenzen auf aktuelle offizielle Releases mit vollständigen Commit-SHAs setzen.
2. PHP-8.5-, Composer-, Node-, Nginx-, MariaDB- und Typesense-Images aktualisieren. Die Debian-/Alpine-Basis, Systempakete und erforderlichen Erweiterungen mitprüfen.
3. Identische Versionen und Digests in Dockerfiles, Compose-Dateien, Testdiensten und der Image-Scan-Matrix konsistent halten. `nextcloud/` bei dieser Suche und allen Ersetzungen ausschließen.
4. Versionen zusätzlich verwendeter Werkzeuge wie Grype aktualisieren und etwaige neue Runner- oder Berechtigungsanforderungen berücksichtigen.
5. Die tatsächlich verwendeten, neu gebauten Images scannen; vorhandene Patches nur nach überprüfter Behebung entfernen. Sicherheitsprüfungen nicht lockern, um ein Update durchzubringen.
6. Bei Änderungen von MariaDB und Typesense die vorhandenen Initialisierungs-, Persistenz-, Upgrade-, Wiederherstellungs- und Anwendungsprüfungen heranziehen. Erforderliche Migrationsschritte für einen späteren Betrieb dokumentieren.
7. Bestehende Dependabot-Einträge für die aktualisierten Manifeste und Images prüfen. Nextcloud weder in zusätzliche Updates noch in neue Scan-Matrizen aufnehmen.

## 4. PHP-8.6-Probe mit unveränderten Paketversionen

Der neue Check soll die Kompatibilität des eingecheckten Abhängigkeitsstands feststellen. Ein frisches Auflösen auf andere Paketversionen darf kein positives Ergebnis für das vorhandene Lockfile erzeugen.

1. Eine isolierte Arbeitskopie für den Probelauf anlegen. Die eingecheckten Manifeste und Lockfiles werden durch die CI nicht verändert oder zurückgeschrieben.
2. Die ursprüngliche PHP-Vorgabe separat erfassen und im Bericht anzeigen. Nur in der Arbeitskopie die eigene Root-PHP-Vorgabe um die 8.6-Reihe erweitern.
3. Ausschließlich die dazugehörigen Root-/Lockfile-Metadaten konsistent nachführen. Die Umsetzung verwendet Composers eigene `Locker::getContentHash`-Funktion aus der geprüften PHAR und erweitert nur die Root-Vorgabe, `platform.php` im Lockfile sowie den Content-Hash. Dadurch ist keine erneute Paketauflösung über `update --lock` erforderlich.
4. Vor und nach diesem Metadatenschritt Paketnamen, Versionen sowie Source-/Dist-Referenzen aller Produktions- und Entwicklungsabhängigkeiten vergleichen. Jede unerwartete Änderung macht die Probe ungültig. Auch unveränderte Requirements der Pakete und die Ausgangsintegrität des Lockfiles prüfen.
5. Die Installation anschließend aus diesem Paketstand unter echtem PHP 8.6 durchführen, einschließlich Entwicklungsabhängigkeiten und benötigter Composer-Skripte. Plattformprüfungen und die vorhandene Composer-Sicherheitsrichtlinie bleiben aktiv.
6. Keine PHP-Version über `config.platform.php` vortäuschen und keine Plattformanforderungen von Abhängigkeiten ignorieren. Ein Paket mit ausschließender PHP-Vorgabe ist ein echter Kompatibilitätsblocker.
7. Der Bericht bezeichnet ein positives Ergebnis als technische Kompatibilität der getesteten Anwendung und Paketversionen unter der isoliert erweiterten Root-Vorgabe. Er zeigt daneben ausdrücklich, dass die produktive Vorgabe weiterhin PHP 8.5 ist. Eine Produktivfreigabe von 8.6 wird durch diese Probe nicht automatisch vorgenommen.

## 5. Neuer Workflow und Abfolge

Neue Datei: `.github/workflows/php86-compatibility.yml`.

### 5.1 Auslöser und Laufzeit

- `pull_request` gegen `main`, `push` auf `main` und `workflow_dispatch`.
- Täglicher Zeitplan: `17 5 * * *`, also 05:17 UTC beziehungsweise 06:17 MEZ / 07:17 MESZ. Geplante Läufe prüfen den Standardbranch, auch wenn kein neuer Commit vorliegt.
- PHP explizit auf die Reihe `8.6` setzen. Vor dem stabilen Release den verfügbaren Vorab-Build dieser Reihe verwenden, danach deren aktuelle stabile Version. Kein beweglicher Selektor wie `master`, der später PHP 8.7 prüfen könnte.
- Den tatsächlichen Interpreter, seine vollständige Version und Erweiterungen prüfen und protokollieren. Versehentliche Ausführung unter einer anderen PHP-Reihe ergibt niemals „kompatibel“.
- Aktuelle Builds bei jedem Lauf anfordern. Cache-Schlüssel nach PHP-Reihe, Plattform und Lockfiles trennen; Download-Caches bevorzugen, keinen ungeprüften `vendor`-Baum aus PHP 8.5 übernehmen.
- `contents: read`, keine Produktionssecrets und keine Schreibrechte auf Branches oder Pull Requests. Fork-PRs müssen mit denselben Leserechten funktionieren; kein `pull_request_target`.
- Laufzeiten begrenzen und überholte PR-Läufe abbrechen. Für Push, Zeitplan und manuelle Läufe dieselbe Konkurrenzgruppe pro Branch verwenden, damit sich identische Proben nicht unnötig überlagern.

### 5.2 Aufeinander aufbauende Stufen

Ein Probe-Job führt die Stufen nacheinander aus und verwendet seine Arbeitskopie, installierte Abhängigkeiten und gebaute Assets erneut. Ein kleiner abschließender Berichtsjob übernimmt Ergebnis und Zusammenfassung.

| Stufe | Inhalt | Voraussetzung |
| --- | --- | --- |
| 0: Umgebung | Checkout, PHP 8.6, Composer und erforderliche Erweiterungen bereitstellen; Versionen und Ausgangsdateien prüfen. | Workflow gestartet. |
| 1: Abhängigkeiten | Isolierte Root-Anpassung, Integritätsvergleich, Lockfile-Installation, Plattformprüfung und Composer-Skripte. Blockierende Paketvorgaben erfassen. | Stufe 0 erfolgreich. |
| 2: Anwendung und Assets | Testumgebung mit temporärem Anwendungsschlüssel, SQLite und Testkonfiguration vorbereiten; Laravel starten können; npm-Abhängigkeiten installieren und Assets einmal bauen. | Stufe 1 erfolgreich. |
| 3: PHP-Tests | Vollständige bestehende Unit-, Feature- und Integration-Suiten unter PHP 8.6 ausführen; vorhandene Architektur-/Qualitätsregeln berücksichtigen. | Stufe 2 erfolgreich. |
| 4: MariaDB | Erst jetzt den isolierten MariaDB-Testcontainer starten; Migrationen und alle drei vorhandenen MariaDB-Konfigurationen prüfen. | Stufe 3 erfolgreich. |
| 5: Pest Browser | Erst jetzt benötigte Browser und Systemabhängigkeiten installieren; vorhandene Pest-Browser-Regression ausführen. | Stufe 4 erfolgreich. |
| 6: Chromium | Bestehende Playwright-Suite für Chromium unter dem PHP-8.6-Webserver ausführen. | Stufe 5 erfolgreich. |
| 7: Firefox | Bestehende Playwright-Suite für Firefox unter dem PHP-8.6-Webserver ausführen. | Stufe 6 erfolgreich. |
| Bericht und Aufräumen | Stufenstatus, ersten Blocker und Logs sichern; gestartete Testdienste beenden. | Auch nach fehlgeschlagenen Prüfstufen. |

Weitere Regeln für diese Abfolge:

- Den MariaDB-Container nicht als bedingungslos vorab startenden Workflow-Service konfigurieren. Start und Initialisierung gehören zu Stufe 4.
- Bereits bei einer nicht installierbaren Abhängigkeit entfallen Asset-Build, PHP-Tests, Datenbank und Browser vollständig.
- Folgestufen prüfen das tatsächliche Ergebnis ihrer Voraussetzungen. `continue-on-error` allein reicht dafür nicht, weil es das nach außen sichtbare Schrittergebnis verändert.
- Bei fehlgeschlagener Chromium-Prüfung Firefox auslassen und den Grund im Bericht zeigen. Innerhalb einer Stufe vorhandenes Abbruchverhalten und begrenzte Test-Retries beibehalten.
- PHP-8.6-Prüfungen ohne zusätzliche Coverage-Messung ausführen. Bestehende PHP-8.5-Coverage und deren Schwellen bleiben maßgeblich.
- In den MariaDB-Konfigurationen das vorhandene Verbot stillschweigend übersprungener Tests beibehalten. Pflichtstufen dürfen für ein positives Gesamtergebnis nicht fehlen.
- Für Browser einen frischen PHP-8.6-Webserver verwenden; keinen bereits laufenden Server oder Docker-PHP-8.5 als Ersatz zulassen. Browser-Datenbanken und Anwendungscaches isolieren.
- Bestehende Testausschlüsse, Assertions und Warnungsrichtlinien nicht für den neuen Lauf abschwächen. Relevante PHP-Deprecations im Bericht sichtbar machen.

### 5.3 Ergebnis bei grünem Check

| Kompatibilitätsstatus | Bedeutung | Sichtbarer Berichtscheck |
| --- | --- | --- |
| `compatible` | Alle Pflichtstufen wurden mit dem geprüften Paketstand erfolgreich ausgeführt. | `PHP 8.6: kompatibel` |
| `incompatible` | Paketanforderungen, Anwendungsstart oder ausgeführte Tests zeigen einen konkreten Blocker. | `PHP 8.6: nicht kompatibel` |
| `unavailable` | Eine belastbare Aussage war wegen Bereitstellungs-, Netzwerk-, Integritäts- oder sonstiger Prüfprobleme nicht möglich. | `PHP 8.6: nicht prüfbar` |

Erwartete negative Prüfergebnisse werden schrittweise mit `continue-on-error` erfasst. Für die Abfolge und Auswertung ist jeweils `steps.<id>.outcome` beziehungsweise der ursprüngliche Exitcode maßgeblich, nicht die nachträglich erfolgreiche `conclusion`.

Die Auswertung startet grundsätzlich mit „nicht prüfbar“. Nur der nachgewiesene Erfolg aller Pflichtstufen darf den Status auf „kompatibel“ setzen. Ein allgemeiner Fehlercode ohne ausreichende Diagnose wird nicht automatisch als Inkompatibilität bezeichnet.

Der Probe-Job gibt den Status, die erste blockierende Stufe und die Einzelergebnisse als strukturierte Outputs aus. Der nachgelagerte Berichtsjob verwendet diese Outputs für seinen dynamischen Namen und schreibt eine Zusammenfassung. Fehlende Outputs ergeben „nicht prüfbar“. So ist weder eine Checks-API mit Schreibrechten noch ein README-Badge erforderlich.

Die Zusammenfassung enthält mindestens:

- Commit, Ereignis und Prüfzeitpunkt sowie tatsächlich verwendete PHP-/Composer-Versionen;
- Ausgangs-Root-Vorgabe und ausschließlich für den Probelauf vorgenommene Anpassung;
- Identität des geprüften Lockfiles und Bestätigung der unveränderten Paketversionen;
- Tabelle aller Stufen mit Erfolg, Fehler oder „wegen vorheriger Stufe übersprungen“;
- ersten Blocker einschließlich betroffener Pakete, konkreter Fehlermeldung oder fehlgeschlagener Tests;
- Links zu Logs, Testberichten und bei Bedarf Playwright-Traces/Screenshots.

Ein regulär beendeter Probelauf bleibt auch bei Inkompatibilität grün. Manuell abgebrochene oder von GitHub wegen Runner-/Workflow-Problemen hart beendete Läufe werden nicht nachträglich als erfolgreich oder kompatibel ausgegeben. Ein erreichbarer Berichtsjob kann in solchen Fällen weiterhin „nicht prüfbar“ melden.

## 6. Geplante Dateien

| Bereich | Dateien beziehungsweise Verzeichnisse |
| --- | --- |
| PHP-Abhängigkeiten | `composer.json`, `composer.lock`; migrationsbedingt betroffene Anwendung, Konfiguration und Tests. |
| JavaScript und Werkzeuge | `package.json`, `package-lock.json`, `.node-version`, `.nvmrc`, bei Bedarf `.npmrc` und Build-/Testkonfiguration. |
| npm-Sicherheitskorrekturen | `docker/npm-security/`, `docker/patch-npm-security.sh` und betroffene Aufrufe. |
| Images | `Dockerfile`, `docker/*.Dockerfile`, Compose-Dateien im Projektstamm und zugehörige Verifikationsskripte. |
| Bestehende CI | `.github/workflows/`, `.github/actions/build-assets/action.yml`, `.github/dependabot.yml`. |
| Neue Kompatibilitätsprüfung | `.github/workflows/php86-compatibility.yml`; kleine Hilfsskripte unter `tools/ci/` für isolierte Metadaten, Stufensteuerung und Ergebnisaufbereitung, soweit erforderlich. |
| Neue Verifikation | Gezielte Tests unter `tests/Node/` für die ausführbare Steuerungs-/Ergebnislogik; vorhandene Testinfrastruktur weiterverwenden. |
| Dokumentation | `README.md` für aktualisierte Voraussetzungen und Beschreibung des Checks, ohne neues Badge; dieser Plan und ein kompakter Versions-/Validierungsnachweis. |
| Ausgenommen | Sämtliche Dateien unter `nextcloud/`. |

## 7. Verifikation

### 7.1 Aktualisierter produktiver Stack

1. Frische Installation aus beiden Projekt-Lockfiles; zusätzlich das npm-Sicherheits-Lockfile prüfen, solange es benötigt wird.
2. Composer-Validierung, tatsächliche Plattformanforderungen, Composer-/npm-Audits und Installationsskript-Policy prüfen.
3. Assets bauen; vollständige PHP-, Vitest-, Node-, MariaDB- und bestehende Browser-Prüfungen unter dem aktualisierten regulären Stack ausführen. Vorhandene Qualitäts- und Mutation-Gates beibehalten und prüfen.
4. Production-, Development- und Test-Images bauen; die tatsächlich eingesetzten Images scannen und vorhandene Deployment-, Upgrade- und Recovery-Prüfungen ausführen, soweit die betroffenen Komponenten geändert wurden.
5. Compose-Konfigurationen und Versionsreferenzen auf Konsistenz prüfen; `nextcloud/` ausdrücklich ausnehmen.
6. Neue oder verbleibende Sicherheitsmeldungen und Versionsblocker mit Ursache und verfügbarem Fix dokumentieren. Ein unbehobener Befund gilt nicht als erledigt.

### 7.2 Steuerung und Status des neuen Checks

Die ausführbare Steuerungslogik mit kontrollierten Ergebnissen der aufgerufenen Befehle prüfen. Dabei insbesondere nachweisen, dass teure Befehle nach einem frühen Fehler tatsächlich nicht aufgerufen werden.

| Szenario | Erwartung |
| --- | --- |
| Alle Stufen erfolgreich | Grüner Check mit „kompatibel“; jede Pflichtstufe und die getesteten Versionen dokumentiert. |
| Paket schließt PHP 8.6 aus | „Nicht kompatibel“; keine Assets, PHP-Tests, MariaDB oder Browser starten. |
| PHP-Test schlägt fehl | „Nicht kompatibel“; Datenbank und Browser bleiben aus. |
| MariaDB-Test schlägt fehl | „Nicht kompatibel“; Browser-Installation und Browser-Tests bleiben aus. |
| Pest-Browser-/Chromium-Test schlägt fehl | „Nicht kompatibel“; nachfolgende Browser-Stufen bleiben aus. |
| PHP-Build oder Download nicht verfügbar | „Nicht prüfbar“; übersprungene Stufen erzeugen kein positives Ergebnis. |
| Falscher PHP-Interpreter oder unerwartet geänderte Paketversion | „Nicht prüfbar“; Integritäts-/Umgebungsfehler klar benannt. |
| Fehlende oder beschädigte Ergebnisdaten | „Nicht prüfbar“; kein impliziter Erfolg. |
| Negativer Kompatibilitätstest | Probe- und Berichtsjob bleiben erfolgreich; vorhandene PHP-8.5- und Security-Gates werden nicht abgeschwächt. |

Zusätzlich die Workflow-Syntax und Expressions mit `actionlint` prüfen, Ergebnisnamen und Zusammenfassung verifizieren und die Bedingungen gegen tatsächliche Step-Outcomes kontrollieren. Tests für diese Steuerung sind erforderlich; zusätzliche Tests, die lediglich Versionsnummern oder Markdown-Inhalte duplizieren, sind nicht vorgesehen.

Die tatsächliche GitHub-Ausführung ist der abschließende Nachweis für Check-Anzeige und gehostete PHP-8.6-Umgebung. Lokal nicht verfügbare Plattformen oder noch nicht ausgeführte Remote-Prüfungen im Ergebnis ausdrücklich benennen.

## 8. Umsetzungsreihenfolge und Abschlusskriterien

1. Zielversionen und Sicherheitsstand inventarisieren; Nextcloud-Ausschluss festhalten.
2. Composer-, npm-, Tooling-, Actions- und Image-Upgrades samt notwendigen Migrationen umsetzen.
3. Den aktualisierten PHP-8.5-Stack mit den bestehenden Prüfungen absichern.
4. Die isolierte PHP-8.6-Probe, abhängige Stufen und Statusauswertung implementieren.
5. Frühe Abbrüche, Fehlerklassifikation und grüne negative Ergebnisse gezielt verifizieren.
6. Dokumentation und Versions-/Validierungsnachweis abschließen; finalen Diff einschließlich unverändertem `nextcloud/` prüfen.

Abgeschlossen ist die Umsetzung, wenn sämtliche berücksichtigten Abhängigkeiten auf dem belegten aktuellen sicheren Stand sind oder konkrete, nicht verdeckte Upstream-Blocker dokumentiert wurden; die regulären Pflichtprüfungen bestehen; und der zusätzliche Check seinen tatsächlichen PHP-8.6-Kompatibilitätsstatus sichtbar, stufenweise und ohne Blockade bei Inkompatibilität ermittelt.

## 9. Technische Referenzen

- [PHP 8.6: offizieller Zeitplan](https://wiki.php.net/todo/php86)
- [setup-php: unterstützte PHP-Reihen und Installation](https://github.com/shivammathur/setup-php)
- [Composer: CLI, Lockfile-Aktualisierung und Plattformanforderungen](https://getcomposer.org/doc/03-cli.md)
- [GitHub Actions: Kontexte einschließlich Step-Outcomes und Job-Outputs](https://docs.github.com/en/actions/reference/workflows-and-actions/contexts)
- [GitHub Actions: Expressions und Statusfunktionen](https://docs.github.com/en/actions/reference/workflows-and-actions/expressions)

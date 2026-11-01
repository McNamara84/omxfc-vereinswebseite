# Abhängigkeiten und PHP 8.6: Umsetzung und Prüfnachweise

Stand: 1. November 2026. Grundlage ist der [abgestimmte Implementierungsplan](Implementierungsplan-Abhaengigkeiten-PHP-8.6.md).

Die Abhängigkeiten wurden auf die aktuellen miteinander kompatiblen stabilen Releases aktualisiert. PHP 8.5 bleibt die produktive Vorgabe. Ein zusätzlicher unverbindlicher CI-Check prüft PHP 8.6 stufenweise bis zu den Browsern und zeigt seinen Kompatibilitätsstatus trotz erfolgreichem Check ausdrücklich an. Nextcloud wurde nicht verändert.

## Aktualisierte Versionen

| Bestandteil | Bisher | Jetzt |
| --- | --- | --- |
| Node.js | 26.12.1 | 26.13.0 LTS |
| Composer | 2.10.3 | 2.11.1, PHAR und Container getrennt mit SHA-256 gepinnt |
| Laravel | 13.38.0 | 13.39.0 |
| Intervention Image | 4.4.0 | 4.4.1 |
| maryUI | 2.9.10 | 2.9.11 |
| Pest / PHPUnit | 5.3.3 / 13.4.3 | 5.3.4 / 13.4.5 |
| Pest Browser | 5.1.2 | 5.2.0 |
| Pest PHPStan / PHPStan | 5.2.2 / 2.3.1 | 5.3.0 / 2.3.4 |
| Rector | 2.7.0 | 2.8.3 |
| Symfony-Komponenten | 8.1.x | 8.1.10, soweit für die jeweilige Komponente veröffentlicht |
| Three.js | 0.186.1 | 0.187.0 |
| npm-Sicherheitspatch `ip-address` | 10.7.4 | 10.7.5 |
| Selector-Parser-Override | 7.1.6 | 7.1.7 |

Die vollständige Composer-Auflösung aktualisiert 46 Pakete und entfernt den nicht mehr benötigten `theseer/tokenizer`. Das Lockfile enthält 201 Produktions- und Entwicklungsabhängigkeiten. Unter anderem sind auch Guzzle 8.3.1, CommonMark 2.10.7, Carbon 3.15.1, League URI 7.9.1 und ParaTest 7.27.5 aktualisiert. Beide npm-Lockfiles wurden neu aufgelöst und geprüft.

Die Versionsabfragen stammen aus [Packagist](https://repo.packagist.org/packages.json), der [npm-Registry](https://registry.npmjs.org/), dem [Node-Releaseverzeichnis](https://nodejs.org/dist/index.json) und den [Composer-Releaseinformationen](https://getcomposer.org/versions).

## Actions und Images

Die Release-Tags und vollständigen Commit-SHAs aller 15 zuvor verwendeten externen Actions wurden mit den offiziellen Repositories abgeglichen. Sie entsprachen bereits ihren aktuellen Releases.

Die Badge-Action `timkrase/phpunit-coverage-badge` wurde dennoch entfernt: Ihr [aktuelles Dockerfile](https://github.com/timkrase/phpunit-coverage-badge/blob/b2cec23f5564ebf1030a6aa5b1e0fcadc5674d79/Dockerfile) verwendet PHP 7.4 und lädt Composer ohne Versionsfixierung. Das neue lokale Skript `tools/testing/php-coverage-badge.mjs` erzeugt dieselbe PHP-Coverage-Anzeige aus dem bereits validierten PHP-Snapshot. Dafür werden weder ein weiterer Container noch zusätzliche npm-Pakete benötigt. Die übrigen 14 Actions bleiben mit ihren geprüften Release-SHAs fixiert.

Aktuelle offizielle Manifest-Digests wurden für folgende Images übernommen:

- Composer 2.11.1;
- PHP 8.5.12 FPM und CLI auf Debian Trixie, Neubauten vom 31. Oktober;
- Node 26.13.0 auf Alpine 3.24 und Debian Trixie Slim;
- Nginx 1.31.8 Alpine 3.24 Slim und MariaDB 13.0.2;
- MariaDB 12.3.3 als weiterhin benötigter Ausgangspunkt des Upgrade-/Rollback-Tests.

Typesense 30.2 ist weiterhin das aktuelle stabile Release; der bestehende Digest bleibt bestehen. Die gehärteten Service-Images wurden mit den verfügbaren Betriebssystemupdates neu gebaut. Vollständige SHAs und Digests stehen in den jeweiligen Workflows und Dockerfiles. Die private, absichtlich nicht versionierte `docker-compose.yml` wurde lokal ebenfalls an die neuen Service-Digests angepasst und wird nicht dem Commit hinzugefügt.

Development- und Playwright-PHP-Images enthalten jetzt die `sqlite3`-CLI, die Laravels Schema-Dump bei dateibasierten Browser-Tests benötigt. Temporäre lokale Prüfberichte und Werkzeuge unter `storage/framework/testing/` werden über `.dockerignore` vom Buildkontext ausgeschlossen.

## Neuer PHP-8.6-Check

Datei: `.github/workflows/php86-compatibility.yml`.

Auslöser sind PRs gegen `main`, Pushes auf `main`, tägliche Läufe um 05:17 UTC sowie manuelle Läufe. Die PHP-Reihe ist ausdrücklich `8.6`; der Interpreter wird zur Laufzeit überprüft. Der Check verwendet nur `contents: read`, keine Produktionssecrets und keine schreibenden PR- oder Checks-API-Aufrufe.

Die Stufen laufen in einer isolierten Arbeitskopie nacheinander: Laufzeit und Integrität, Composer-Installation, Anwendung/Assets, PHP-Tests und Qualitätsregeln, MariaDB, Pest Browser, Chromium und Firefox. Nach einer fehlgeschlagenen Voraussetzung entfallen die Folgestufen. MariaDB und Browser werden erst bei Bedarf bereitgestellt; Abhängigkeiten und Assets werden wiederverwendet.

Ein separater PHP-8.6-Cache speichert ausschließlich Composer-Downloads. Es wird kein PHP-8.5-`vendor`-Baum übernommen. Ein Cache-Dienstausfall verändert die eigentliche Kompatibilitätsaussage nicht.

Nur die eigene Root-PHP-Vorgabe wird in der Kopie erweitert. Die vollständigen Paketmetadaten bleiben unverändert; Composers eigene Hashfunktion hält das Lockfile konsistent. Eine erneute Integritätsprüfung nach Installation und Browsern verhindert ein positives Ergebnis nach unbemerkten Änderungen. Anforderungen der Abhängigkeiten, echte Plattformprüfungen und die bestehenden Sicherheitsrichtlinien bleiben aktiv.

Ein Berichtsjob zeigt `PHP 8.6: kompatibel`, `nicht kompatibel` oder `nicht prüfbar`. Erkannte Inkompatibilität endet erfolgreich. Fehlende Nachweise, Bereitstellungsprobleme und unbekannte Fehler werden nicht als Kompatibilität ausgegeben. Die Zusammenfassung nennt Versionen, Ausgangs-Lockfile, Stufen und ersten Blocker; Diagnoseartefakte werden 14 Tage aufbewahrt. Die vorhandenen verpflichtenden PHP-8.5- und Deployment-Prüfungen bleiben verpflichtend.

Die unabhängigen PHP-Worker erhalten ausdrücklich dieselbe Datenbank wie ihre jeweilige PHPUnit-Konfiguration. Das gilt sowohl für den lokalen Testkoordinator als auch für den neuen CI-Check. Die PHP-Suite verwendet im Speicher liegendes SQLite; Browser verwenden ihre eigene Dateidatenbank.

## Lokale Validierung

| Prüfung | Ergebnis |
| --- | --- |
| Composer-Validierung, Plattformanforderungen und Audit | Erfolgreich; keine Composer-Sicherheitswarnungen |
| Frische npm-Installation, beide npm-Audits | Erfolgreich; keine gemeldeten Paketlücken |
| Produktions-Build und Asset-Build | Erfolgreich |
| Development- und Playwright-PHP-Build | Erfolgreich einschließlich SQLite-CLI |
| PHP 8.5, vollständige reguläre Suite | 4.341 bestanden, 63 bestehende bedingte Skips, 20.980 Assertions |
| Vitest nach dem letzten Parser-Patch | 586 Tests in 40 Dateien bestanden |
| Node einschließlich Deployment-, Abbruch- und Integritätsregressionen | 101 bestanden |
| MariaDB: Maddraxikon / RPG / Veranstaltungs-Baxx | 562 / 21 / 8 bestanden; keine übersprungenen Tests erlaubt |
| PHPStan, Rector, Architektur und Profanity | Erfolgreich |
| Chromium | 283 bestanden, 2 bestehende Skips |
| Firefox | 283 bestanden, 2 bestehende Skips |
| Pest Browser | 1 bestanden, 77 Assertions |
| PHP 8.6.0 RC4: Unit-Suite | 552 bestanden, 2.859 Assertions |
| PHP 8.6.0 RC4: vollständige PHP-Suite | 4.341 bestanden, 63 bestehende bedingte Skips, 20.980 Assertions; Paketintegrität bestätigt |
| PHP 8.6: tatsächliche Plattform und Installations-Probelauf | Erfolgreich mit unverändertem Paketstand und isolierter Root-Anpassung |
| PHP 8.6: absichtlich inkompatible Paketvorgabe | Erfolgreich getestet: Status negativ, Prozess-Exit 0, teure Folgestufen vollständig übersprungen |
| MariaDB-Serviceimage: Initialisierung, Benutzerwechsel und Persistenz | Erfolgreich, auch nach Neustart |
| Reales MariaDB-Upgrade 12.3.3 auf 13.0.2 und Rollback | Erfolgreich einschließlich Daten, UTF-8, Rechten, Views, Routinen, Events und Fremdschlüsseln |
| Workflow-Syntax und Expressions | `actionlint` 1.7.12 erfolgreich |

Nach dem letzten Selector-Parser-Patch wurden alle neu gebauten Asset-Dateien mit SHA-256 gegen die bereits im Browser geprüften Assets verglichen: Die Inhalte sind vollständig identisch. Ein weiterer vollständiger Browserlauf wegen dieses reinen Werkzeug-Patches ist deshalb nicht erforderlich.

Die anfängliche RPG-Prüfung deckte die geerbte falsche Worker-Datenbank auf; die betroffenen MariaDB-Suiten wurden nach der Korrektur vollständig wiederholt. Die erste lokale Pest-Browser-Prüfung zeigte die fehlende SQLite-CLI; nach deren Ergänzung besteht die Regression. Es wurden keine Assertions oder Sicherheitsrichtlinien abgeschwächt.

## Nachprüfung des ersten GitHub-Laufs

Der [erste PHP-8.6-Lauf](https://github.com/McNamara84/omxfc-vereinswebseite/actions/runs/41325064930/job/124903171934) meldete nach 3.938 erfolgreichen Projekttests den ersten Blocker bei `Tests\Unit\TiaWorkflowCommandTest`. Der Test konnte `.github/workflows/tia-baseline.yml` nicht lesen; `file_get_contents` lieferte `false`. Die übrigen Stufen wurden daraufhin wie vorgesehen ausgelassen.

Die Ursache war die Vorbereitung der Arbeitskopie: `git archive HEAD` berücksichtigt `/.github export-ignore` aus `.gitattributes`. Dadurch fehlten im Probelauf die CI-Dateien, obwohl sie im ausgecheckten Repository vorhanden sind. Dieser Fehler ist kein Nachweis einer Pest- oder PHP-8.6-Inkompatibilität und erfordert kein Abhängigkeitsupdate.

Der Workflow verwendet deshalb jetzt `git checkout-index --all --prefix=…/`. Das übernimmt sämtliche versionierten Dateien einschließlich der für Tests benötigten, beim Distributionsexport ausgelassenen Dateien. Private unversionierte Dateien und das ursprüngliche `.git`-Verzeichnis werden weiterhin nicht kopiert; Änderungen der Probe bleiben von der Quelle isoliert. Die Exportregeln und die bestehenden PHP-Testassertions bleiben erhalten.

Ein neuer Regressionstest führt den tatsächlichen Bash-Vorbereitungsschritt mit einem Git-Testrepository aus. Er reproduziert zunächst den Verlust der Workflow-Datei durch `git archive` und prüft anschließend die vollständige isolierte Kopie, Dateinamen mit Leerzeichen und den Ausschluss unversionierter Dateien. Alle 15 Tests dieser Node-Prüfdatei bestehen unter Linux.

Zusätzlich wurden die von Workflow-Dateien abhängigen PHP-Tests und die nachfolgenden PHPStan-, Rector-, Architektur- und Profanity-Prüfungen mit einer Kopie des echten Repository-Indexes unter PHP 8.6.0 RC4 erfolgreich ausgeführt. `actionlint` bestätigt den geänderten Workflow. Ein neuer gehosteter Lauf nach Übernahme des Fixes muss noch zeigen, ob die späteren MariaDB- und Browserstufen unter dem vom Runner verwendeten PHP-8.6-Build weitere Blocker finden.

## Sicherheitsbefunde und verbleibende Grenzen

Grype 0.121.0 meldet für die neu gebauten Produktions-, Nginx-, MariaDB- und Typesense-Images **keine behebbaren High-/Critical-Befunde**. Die unveränderte vorhandene CI-Richtlinie blockiert solche Befunde weiterhin.

Das bedeutet nicht, dass alle Scannerbefunde behoben sind. Der vollständige Scan enthält auch Befunde ohne für die geprüfte Distribution gemeldeten Fix beziehungsweise mit `wont-fix`-Status. Die folgenden Zahlen zählen Paket-/Schwachstellen-Treffer, keine eindeutigen CVEs:

| Geprüftes Image | Critical | High | Medium | Low | Negligible |
| --- | ---: | ---: | ---: | ---: | ---: |
| Produktion, PHP 8.5.12/Trixie | 42 | 237 | 142 | 22 | 689 |
| Nginx 1.31.8/Alpine | 0 | 0 | 3 | 0 | 0 |
| MariaDB 13.0.2 | 0 | 0 | 1.259 | 10 | 0 |
| Typesense 30.2 | 0 | 0 | 92 | 19 | 8 |

Beispielsweise enthält Trixie weiterhin MariaDB-Clientpakete 11.8.6 mit entsprechend gemeldeten Befunden. Der Client wird für die vorhandene Datenbankwartung benötigt und wurde nicht entfernt. Diese Befunde sind weder als behoben ausgewiesen noch durch Scanner-Ausnahmen verborgen. Die vollständigen lokalen JSON-Berichte liegen im ignorierten Verzeichnis `storage/framework/testing/`; die vorhandene CI veröffentlicht ihre eigenen Scan-Artefakte.

Zwei transitive Major-Upgrades bleiben durch Upstream-Anforderungen blockiert: maryUI 2.9.11 verlangt `jfcherng/php-diff` 6.x statt des verfügbaren 7.0.1; dieser Zweig verlangt wiederum Sequence Matcher 4.x statt 5.x. Die Constraints werden nicht mit Alias-Versionen umgangen. npm 12.2.1 benötigt den kompatiblen Undici-6-Zweig; dessen aktuelle Patchversion 6.29.1 bleibt erhalten. Der Selector-Parser-Override bleibt notwendig, weil das aktuelle Tailwind Typography weiterhin Version 6.0.10 verlangt; der geprüfte Override wurde auf 7.1.7 angehoben.

Die vollständige PHP-8.6-CI einschließlich deren eigener MariaDB- und Browserstufen sowie die tatsächliche GitHub-Anzeige werden erst nach einem Push auf GitHub ausgeführt. Lokale Prüfungen ersetzen diesen gehosteten Nachweis nicht. Es wurden weder ein Push noch ein Deployment vorgenommen. Die vorhandenen Coverage- und Mutation-Gates bleiben in der CI aktiv; eine neue vollständige lokale Coverage-/Mutationserhebung gehört nicht zu diesen Prüfnachweisen.

## Reproduktion

- Abhängigkeiten: `composer validate --strict --no-check-publish`, `composer audit --locked --abandoned=fail`, `composer check-platform-reqs --lock`, `npm ci` und `npm audit --audit-level=moderate`.
- Linux-Teststack: `npm run test:all`; benötigt die dokumentierten Testimages und eine isolierte Testdatenbank. Für Host-Ausführung `TEST_RUNTIME=host` verwenden.
- Steuerungsregressionen: `node --test tests/Node/php86-compatibility.test.mjs tests/Node/php-coverage-badge.test.mjs tests/Node/test-runner.test.mjs`.
- MariaDB-Service: `bash docker/verify-mariadb-image.sh <image>` und `bash docker/verify-mariadb-upgrade.sh <image>`.
- Workflow-Lint: `actionlint`; die lokale Version wurde aus dem offiziellen Release mit überprüfter SHA-256-Summe verwendet.

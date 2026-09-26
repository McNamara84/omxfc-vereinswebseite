# OMFXC Vereinswebseite

![Laravel 13](https://img.shields.io/badge/laravel-13-red?logo=laravel&style=flat)
![PHP 8.5](https://img.shields.io/badge/php-8.5-blue?logo=php)
![Node 26](https://img.shields.io/badge/node-26-5FA04E?logo=node.js&logoColor=white)
![JS Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/js-coverage.svg)
![PHP Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/php-coverage.svg)
[![License](https://img.shields.io/badge/license-GPLv3-green)](LICENSE)
[![E2E Tests](https://github.com/McNamara84/omxfc-vereinswebseite/actions/workflows/playwright.yml/badge.svg)](https://github.com/McNamara84/omxfc-vereinswebseite/actions/workflows/playwright.yml)

Offizielle Laravel-13-Anwendung für die Vereinswebseite des **Offizieller MADDRAX Fanclub (OMFXC)**. Das Projekt kombiniert eine moderne, barrierearme Oberfläche auf Basis von Tailwind CSS, Alpine.js und Livewire mit einem umfassenden Funktionsumfang für Mitgliederverwaltung, Vereinskommunikation und Content-Pflege.

## Inhaltsverzeichnis

- [OMFXC Vereinswebseite](#omfxc-vereinswebseite)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [Hauptfunktionen](#hauptfunktionen)
  - [Technologie-Stack](#technologie-stack)
  - [Voraussetzungen](#voraussetzungen)
  - [Lokale Entwicklung](#lokale-entwicklung)
    - [Docker Compose Dev-Stack (empfohlen)](#docker-compose-dev-stack-empfohlen)
    - [Klassische Host-Entwicklung (optional)](#klassische-host-entwicklung-optional)
    - [Entwicklungsumgebung starten](#entwicklungsumgebung-starten)
    - [Datenbank seeden](#datenbank-seeden)
  - [Maddrax-Charakter-Editor und Regelquellen](#maddrax-charakter-editor-und-regelquellen)
  - [Maddraxikon-Baxx-Regeln verwalten](#maddraxikon-baxx-regeln-verwalten)
  - [Maddraxikon-Bewertungen in Rezensionen](#maddraxikon-bewertungen-in-rezensionen)
  - [Cover-Bewertungen](#cover-bewertungen)
  - [Maddrax-Fantreffen 2026 Event-System](#maddrax-fantreffen-2026-event-system)
    - [Funktionen](#funktionen)
    - [Konfiguration](#konfiguration)
    - [Preisstruktur](#preisstruktur)
    - [Zugriffskontrolle](#zugriffskontrolle)
  - [Tests \& Qualitätssicherung](#tests--qualitätssicherung)
  - [Deployment](#deployment)
  - [Nützliche Artisan-Befehle](#nützliche-artisan-befehle)
    - [Sitemap erzeugen](#sitemap-erzeugen)
    - [Scheduler in Produktion](#scheduler-in-produktion)
  - [Support](#support)

## Hauptfunktionen

- **Öffentliche Vereinsseiten** für Chronik, Termine, Ehrenmitglieder, Satzung und Spendenkampagnen.
- **Online-Mitgliedsantrag** inkl. automatisierter Bestätigungs- und Freigabeprozesse.
- **Event-Management:** Maddrax-Fantreffen 2026 Anmeldesystem mit PayPal-Integration, T-Shirt-Bestellung und Admin-Dashboard für Vorstand/Kassenwart.
- **Mitgliederbereich** mit Dashboard, Aufgabenverwaltung, Newslettern, Belohnungen und Audiobereich.
- **Interaktive Mitgliederkarte** (Leaflet + MarkerCluster) mit aktualisiertem Cache via Scheduler.
- **Arbeitsgruppen-Management** mit Rollen, Teamverwaltung und CSV-Export der Mitgliederlisten.
- **Maddrax-Charakter-Editor** mit wählbaren Regelquellen, gespeicherten Charakteren und PDF-Bögen.
- **Meeting- und Kassenbuchmodule** zur Organisation von Vereinstreffen und Finanzverwaltung.
- **Maddraxiversum-Minispiele** und weitere Community-Features (Cover-Bewertungen, Rezensionen, Romantausch, Hörbücher).

## Technologie-Stack

- **Backend:** Laravel 13, Jetstream, Sanctum, Scout mit Typesense, Livewire 4, maryUI 2.9 sowie Spatie PDF (Dompdf) & Sitemap.
- **Frontend:** Tailwind CSS, Alpine.js, Vite, Chart.js, Simple Datatables, Leaflet sowie lokal gebündelte Figtree- und Space-Grotesk-Schriften.
- **Testing:** PHPUnit 13, Vitest 5, Playwright inkl. axe-core für Accessibility-Regressionen.
- **Tooling & DevOps:** Laravel Pint, Dockerfile mit Production- und Development-Target, docker-compose.dev.yml für den lokalen Stack.

## Voraussetzungen

| Komponente       | Version / Hinweis                                      |
|------------------|---------------------------------------------------------|
| Docker Desktop / Docker Engine | Empfohlen für die lokale Entwicklung mit `docker-compose.dev.yml` |
| PHP              | 8.5.x inklusive Extensions: `uri`, `zip`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `bcmath`, `gd`, `pcntl` |
| Composer         | 2.10.x, nur für klassische Host-Entwicklung nötig        |
| Node.js & npm    | Node 26.x (`.node-version`) und npm 12.0.2 (`packageManager`), nur für klassische Host-Entwicklung nötig |
| Datenbank        | MariaDB / MySQL für Runtime, SQLite für schnelle Standardtests |

> **Empfehlung:** Nutze lokal den produktionsnahen Docker-Stack aus `docker-compose.dev.yml`. Die klassische Host-Entwicklung bleibt als Fallback erhalten.

## Lokale Entwicklung

Für neue Entwickler ist der Docker-Compose-Dev-Stack der Standard-Onboarding-Pfad. Die klassische Host-Entwicklung bleibt nur als Fallback für Spezialfälle erhalten.

### Docker Compose Dev-Stack (empfohlen)

1. Repository klonen und ins Projektverzeichnis wechseln.
2. Die lokale Docker-Env-Datei anlegen:
   ```bash
  cp .env.docker.dev.example .env.docker.dev.local
   ```
3. Den Platzhalter `DOCKER_DEV_APP_KEY=base64:CHANGE_ME` in `.env.docker.dev.local` durch einen lokal generierten Schlüssel ersetzen, zum Beispiel mit:
  ```bash
  npm run docker:dev:key:generate
  ```
4. Falls du externe Test- oder Sandbox-Credentials brauchst, trage sie nur in `.env.docker.dev.local` ein.
5. Den Stack bauen und starten:
   ```bash
  npm run docker:dev:up
   ```
6. Anwendung und HMR stehen danach standardmäßig hier bereit:
  - App: `http://localhost:8080`
  - Vite-HMR: `http://localhost:5173`
  - MariaDB (optional von außen): `127.0.0.1:3307`
  - Typesense (optional von außen): `127.0.0.1:8108`

Die App-Container warten auf MariaDB, führen standardmäßig Migrationen aus und starten danach PHP-FPM, Queue-Worker und Vite. Das Verhalten lässt sich über `DOCKER_DEV_AUTO_MIGRATE` in `.env.docker.dev.local` steuern. Bleibt `DOCKER_DEV_APP_KEY` auf `base64:CHANGE_ME` oder leer, brechen App- und Queue-Container bewusst früh mit einer klaren Fehlermeldung ab.

### Klassische Host-Entwicklung (optional)

1. PHP- und Node-Abhängigkeiten installieren:
   ```bash
  composer install
  npm ci
   ```

  npm 12 blockiert Abhängigkeits-Installationsskripte standardmäßig. Die
  einzige Freigabe unter `allowScripts` gilt versionsgenau für
  `fsevents@2.3.3`, dessen natives Installationsskript Vite auf macOS benötigt.
  Ein normales `npm ci` erzwingt die Allowlist und schlägt dank
  `strict-allow-scripts=true` bei neuen ungeprüften Skripten fehl;
  `npm install-scripts ls` dient nur als zusätzliche Inventarliste. Datei-,
  Verzeichnis-, Git- und Remote-Abhängigkeiten sind über `.npmrc` gesperrt.
  Puppeteer/Chromium wird nicht mehr benötigt, da PDF-Exporte ausschließlich
  über Dompdf laufen.

2. Beispiel-Environment kopieren und Applikationsschlüssel erzeugen:
   ```bash
  cp .env.example .env
  php artisan key:generate
   ```
3. Datenbankzugang in `.env` anpassen (z. B. `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
4. Datenbankmigrationen ausführen:
  ```bash
  php artisan migrate
  ```
5. Assets für Produktion kompilieren (optional, Vite-Dev-Server reicht lokal):
  ```bash
  npm run build
  ```

### Entwicklungsumgebung starten

- **Docker-Stack starten / stoppen:**
  ```bash
  npm run docker:dev:up
  npm run docker:dev:down
  ```
- **Docker-Logs folgen:**
  ```bash
  npm run docker:dev:logs
  ```
- **Host-Workflow separat:**
  ```bash
  php artisan serve
  npm run dev
  ```
- **Host-Workflow kombiniert:**
  ```bash
  composer run dev
  ```

Der Host-Workflow startet wie bisher den PHP-Entwicklungsserver, einen `queue:work`-Prozess sowie den Vite-Dev-Server parallel. Für produktionsnahe Entwicklung ist aber der Docker-Stack die Standardempfehlung.

### Datenbank seeden

Für Demodaten stehen Seeder im Ordner `database/seeders` zur Verfügung. Sie lassen sich einzeln oder gesammelt ausführen:

```bash
php artisan db:seed --class=DefaultAdminAndTeamSeeder
php artisan db:seed --class=TodoCategorySeeder
php artisan db:seed
```

Spezielle Seeder wie `TodoPlaywrightSeeder` und `FantreffenPlaywrightSeeder` bereiten End-to-End-Tests vor und sollten nur in Testumgebungen ausgeführt werden.

## Maddrax-Charakter-Editor und Regelquellen

Unter `/rpg/char-editor` ist die **1. Erweiterung von Stefan Küppers** für neue
Charaktere standardmäßig aktiviert. Sie ergänzt Agarther, Marsianer und Morlocks,
die Kultur „Marsianische Städter“ sowie die Ausbildungen Gladiator und Priester.
Die Auswahl gilt pro Charakter. Basisregelwerk und Erweiterung sind in der
Rassen-, Kultur- und Ausbildungsauswahl sowie in den Zusammenfassungen beschriftet.

Die Erweiterung lässt sich unter „Regelquellen“ abschalten. Werden bereits Inhalte
daraus verwendet, nennt der Editor die betroffenen Auswahlen und erhält alle
Eingaben. Über „Charakterdaten ändern“ lassen sich Rasse und Kultur erneut wählen.
Nach dem Wechsel oder Entfernen der betroffenen Inhalte ist das Abschalten möglich.

Aktive Quellen werden mit Kennung, Namen, Autor und Version im Charakter gespeichert
und beim PDF-Export angegeben. Ältere Charaktere ohne Quellenangaben verwenden das
Basisregelwerk. Die Kulturzuordnungen, Regelboni und der Nachrichtentwurf für Stefan
stehen im [Implementierungsplan](docs/RPG-Regelwerk/Implementierungsplan-1-Erweiterung.md).

### Erfahrungspunkte und Charakterverbesserung

Unter **Meine Charaktere** (`/rpg/charaktere`) sieht die aktuelle Leitung der
AG Rollenspiel die Einstiege **EP vergeben** und **Verbesserungen prüfen**.
Die Leitung muss als Besitzer der AG (`teams.user_id`) hinterlegt und zugleich
AG-Mitglied sein. Eine Administratorrolle allein genügt nicht.

Die Vergabe erfasst Abenteuer, Abschlussdatum, Spielzeit und die Kriterien aus
Seite 54 des Regelwerks. Der Server berechnet die EP; abweichende Beträge brauchen
eine Begründung. Positive Vergaben erscheinen im Dashboard. Die Bewertung bleibt
dem Charakterbesitzer und der Leitung vorbehalten.

Besitzer wählen **Charakter verbessern** in der Charakterliste oder in ihrer
Dashboard-Meldung. Nach einer verbindlichen Servervorschau können sie mehrere
Änderungen gemeinsam beantragen. Erst die Genehmigung bucht EP ab und aktualisiert
den Charakter. Ablehnung und Rücknahme verbrauchen keine EP; je Charakter ist ein
offener Antrag möglich. Der Charakterbogen zeigt aktuelle Werte und EP sowie
vollständige Angaben und den Buchungsverlauf auf Folgeseiten.

Die Preise berücksichtigen einzelne Fertigkeitsstufen, doppelte Kosten für
psychische Fertigkeiten, wiederholbare Vorteile und 60 EP für Gestaltwandler.
Alte Charaktere behalten ihren Ausgangsstand. Fehlen die ursprüngliche
Barbaren-Attributswahl oder Ziele früherer Vorteile, ergänzt die Leitung diese
begründet im Charakterverlauf, ohne Werte oder EP zu verändern.
Die fachlichen Festlegungen stehen im
[EP-Implementierungsplan](docs/RPG-Regelwerk/Implementierungsplan-Erfahrungspunkte.md).

Vor der ersten Nutzung die Migrationen mit `php artisan migrate` und die Assets
mit `npm run build` ausführen. Bestandscharaktere beginnen mit null dokumentierten
EP; frühere Abenteuer lassen sich mit ihrem damaligen Abschlussdatum nachtragen.

Gezielte Prüfungen:

```bash
php artisan test --filter 'Rpg|DashboardActivityFeedTest|ActivityFeedTest'
npm run test:coverage -- tests/Vitest/rpg-progression.test.js tests/Vitest/char-editor.test.js tests/Vitest/dashboard-activity-feed.test.js
npm run test:e2e:docker -- tests/e2e/rpg-progression.spec.js --project=chromium
```

`phpunit.rpg-mariadb.xml` prüft echte parallele Buchungen mit getrennten
PHP-Prozessen. Dafür eine **ausschließlich für Tests bestimmte** MariaDB-Datenbank
`omxfc_rpg_test` anlegen und `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`,
`DB_CONNECTION=mysql`, `DB_DATABASE=omxfc_rpg_test` und `APP_ENV=testing` in der
Testumgebung setzen. Dann
`php vendor/bin/pest --configuration phpunit.rpg-mariadb.xml` ausführen.
Der Test migriert diese Datenbank frisch; SQLite-Läufe überspringen diese
separate Testsuite. Die Verbindungsdaten werden an die Worker vererbt.

Der Job **PHP 8.5 RPG Concurrency (MariaDB 12.3)** im Workflow
`.github/workflows/phpunit.yml` führt diese Suite bei Pull Requests gegen
`main` und Pushes auf `main` automatisch aus. Sein eigener MariaDB-Service
erstellt `omxfc_rpg_test`; übersprungene Tests lassen den Job fehlschlagen.

### Würfelaufforderungen und Proben

Unter **Proben** (`/rpg/proben`) fordert die aktuelle Leitung der AG Rollenspiel
Charaktere anderer AG-Mitglieder zu Attributs- oder Fertigkeitsproben auf.
Eine Mehrfachauswahl erstellt getrennte Proben mit gemeinsamen Vorgaben.
Widerstandsproben verbinden zwei Spielercharaktere; einen Gleichstand entscheidet
die Leitung mit einer Begründung. Die NSC-Auswahl ist für einen späteren Ausbau
sichtbar, derzeit aber deaktiviert.

Die Servervorschau übernimmt gespeicherte Charakterwerte. Schwierigkeit und
beschriebene Situationsmodifikatoren wählt die Leitung. Die Werte werden beim
Anfordern festgehalten; spätere Charakterverbesserungen verändern laufende Proben
nicht. Jeder Besitzer würfelt seine Seite einmal über die Webseite. Wiederholte
Anfragen liefern denselben gespeicherten Wurf.

Offene Ergebnisse sehen der jeweilige Besitzer und die Leitung. Bei verdeckten
Proben erhalten Besitzer nur die Aufforderung und eine Bestätigung ihres Wurfs;
Schwierigkeit, zusätzliche Modifikatoren, Würfel und Ausgang werden ihrem Browser
nicht übermittelt. Bei offenen Widerstandsproben bleiben fremde Einzelrechnungen
verborgen, der gemeinsame Ausgang ist für beide Besitzer sichtbar.

Persönliche Dashboard-Hinweise und Probenansichten aktualisieren sich im sichtbaren
Tab ungefähr alle fünf Sekunden. Die Leitung kann offene Proben stornieren.
Abgeschlossene Würfe bleiben im geschützten Verlauf erhalten. Ein Administratorstatus
allein verleiht keine Probenrechte.

Vor der ersten Nutzung `php artisan migrate` und `npm run build` ausführen.
Es werden drei neue Tabellen angelegt; bestehende Charakterwerte und EP bleiben
unverändert. Details stehen im
[Proben-Implementierungsplan](docs/RPG-Regelwerk/Implementierungsplan-Proben.md).

Gezielte Prüfungen:

```bash
php artisan test --filter 'RpgCheck(?!MariaDb)|RpgCharacterStorageTest|DashboardActivityFeedTest|ActivityFeedTest|DeleteAccountTest|DeleteTeamTest'
npm run test:vitest -- tests/Vitest/rpg-checks.test.js
npm run test:e2e:docker -- tests/e2e/rpg-checks.spec.js --project=chromium
```

Die oben beschriebene separate MariaDB-Suite `phpunit.rpg-mariadb.xml` prüft
zusätzlich gleichzeitige Würfe, beide Seiten einer Widerstandsprobe, doppelte
Sammelaufforderungen, konkurrierende Gleichstandsentscheidungen sowie
Stornierungen und Löschungen während des Würfelns.

### Weitere Erweiterungen ergänzen

1. In `app/Support/RpgCharEditorRuleCatalog.php` eine stabile Quellenkennung samt
   Metadaten und Standardaktivierung aufnehmen. Bestehende Kennungen beibehalten.
2. Rassen und Kulturen im Katalog, Ausbildungen in `RpgCharEditorTraining.php` über
   `source` zuordnen. Neue Wahlregeln und Effekte in Servervalidierung und
   `resources/js/alpine/char-editor.js` ergänzen. Quellenauswahl, Filterung und
   Speicherung verwenden bereits den gemeinsamen Katalog.
3. Regeln fachlich testen und die JavaScript-Testdaten mit
   `php tests/Fixtures/update-rpg-extension-rules.php` aktualisieren.
   `RpgCharEditorRuleCatalogTest` prüft, dass diese Daten den PHP-Definitionen entsprechen.
4. `php artisan test --filter Rpg`,
   `npm run test:vitest -- tests/Vitest/char-editor.test.js` und
   `npm run test:e2e:docker -- tests/e2e/char-editor.spec.js --project=chromium` ausführen.

## Maddraxikon-Baxx-Regeln verwalten

Administratoren verwalten die versionierten Regeln unter
`/belohnungen/admin/maddraxikon` über den Bereich „Maddraxikon-Baxx-Regeln“.
Für normale Bearbeitungen wird der Netto-Zuwachs in Bytes innerhalb einer
30-minütigen Sitzung auf derselben Seite bewertet. Neue Artikel besitzen eine
separate Mindestgröße und Baxx-Zahl. Das bestehende Tageslimit von 10 Baxx und
das 24-Stunden-Prüffenster bleiben davon unberührt.

Für die erste Konfiguration in einer bestehenden Installation:

1. Das Deployment einschließlich `php artisan migrate --force` abschließen.
2. In der Maddraxikon-Administration eine leere Regelversion anlegen oder die
   aktuell gültige Version kopieren.
3. Eindeutige Byte-Mindestgrenzen und die zugehörigen Baxx-Werte eintragen.
4. Den Gültigkeitszeitpunkt bewusst in die Zukunft setzen und die Vorschau
   prüfen.
5. Die Version veröffentlichen. Veröffentlichte Versionen sind anschließend
   unveränderlich; Korrekturen erfolgen durch eine neue zukünftige Version.

Bis zum Gültigkeitszeitpunkt der ersten veröffentlichten Version verarbeitet
die Anwendung Beiträge weiterhin mit den bisherigen Legacy-Regeln. Der
fachliche Zeitpunkt der Bearbeitung entscheidet über die anzuwendende Version,
nicht der spätere Auswertungslauf. Bereits abgeschlossene Buchungen werden
nicht rückwirkend verändert.

Nach der Veröffentlichung sind in der Administration die aktuelle und die
nächste geplante Version sowie die Historie zu kontrollieren. Nach Eintritt
des Gültigkeitszeitpunkts sollte außerdem ein Reward-Event geprüft werden: Es
muss die verwendete Policy, den Netto-Zuwachs, die erreichte Stufe und die
resultierenden Baxx als Snapshot enthalten. Eine Vergabe lässt sich bei Bedarf
durch eine neue, global deaktivierte Policy mit zukünftigem Zeitpunkt stoppen,
ohne historische Regeln oder Buchungen zu löschen.

## Maddraxikon-Bewertungen in Rezensionen

Bei aktiver Maddraxikon-Verknüpfung kann die persönliche VoteNY-Bewertung des
Rezensionsautors direkt unter der Überschrift seiner Rezension erscheinen. Die
Anwendung liest dazu aus `Vote` ausschließlich `vote_id`, `vote_actor`,
`vote_page_id`, `vote_value` und `vote_date` sowie `actor_id` und `actor_user`
aus `actor`. Für die exakte Romanzuordnung liest sie zusätzlich `page_id`,
`page_namespace`, `page_title` und
`page_is_redirect` aus `page` sowie `rd_from`, `rd_namespace` und `rd_title`
aus `redirect`. Die Zugriffe erfolgen über die separate Verbindung
`maddraxikon`; lokal wird ein höchstens 60 Minuten sichtbarer Snapshot ohne
`vote_id` gespeichert. Ein vorhandenes MediaWiki-Tabellenpräfix wird über
`MADDRAXIKON_DB_PREFIX` konfiguriert. Der Datenbankbenutzer muss auf diese
Tabellen beschränkte `SELECT`-Rechte besitzen.

Vor der ersten Aktivierung bleiben `MADDRAXIKON_RATINGS_ENABLED=false` und das
Feature damit unsichtbar. Nach Migration und Konfiguration erfolgt der
kontrollierte Erstlauf mit:

```bash
php artisan maddraxikon:map-review-books --dry-run
php artisan maddraxikon:map-review-books
php artisan maddraxikon:sync-review-ratings --dry-run --force
php artisan maddraxikon:sync-review-ratings --force
php artisan maddraxikon:status --skip-api
```

Erst nach erfolgreicher Zuordnung und Synchronisation wird das Feature-Flag
aktiviert. Das Trennen eines Kontos blendet die Bewertung sofort aus; der
nächste erfolgreiche Synchronisationslauf entfernt den Snapshot. Das
Deaktivieren des Flags blendet alle Bewertungen ebenfalls sofort aus und
stoppt Quellabgleich und Bereinigung. Bereits vorhandene Snapshots bleiben für
eine mögliche Reaktivierung lokal gespeichert, sind bei deaktiviertem Flag
jedoch nie sichtbar und werden nach einer Reaktivierung regulär aktualisiert
oder bereinigt.

## Cover-Bewertungen

Freigeschaltete Vereinsmitglieder können die Cover aller sechs gepflegten
Heftreihen unter `/cover-bewertungen` mit 1 bis 5 Brinas bewerten. Nach jeder
Stimme wird unmittelbar ein noch nicht bewertetes Cover angeboten. „Später
bewerten“ gilt nur für die aktuelle Sitzung; eigene Stimmen lassen sich unter
`/cover-bewertungen/meine` ändern oder per Soft Delete zurücknehmen.

Die Übersichtsseite zeigt den Fortschritt und lässt vor dem Start die gewünschte
Serie auswählen. „Bewertung starten“ öffnet eine bildzentrierte Sitzung und
fordert, soweit vom Browser unterstützt, den nativen Vollbildmodus an. Andernfalls
steht automatisch ein browserfüllendes Overlay als Fallback bereit. Das Cover wird
ohne Beschnitt an die verfügbare Fläche angepasst und bei Bedarf auch über seine
natürliche Auflösung hinaus vergrößert. Eine kompakte Leiste hält Brina-Auswahl,
„Später bewerten“ und „Bewertungen beenden“ jederzeit erreichbar.

Die Ergebnisse bleiben anonym. Ein Mitglied sieht ein Cover dort erst nach
der eigenen Stimme und den Durchschnitt erst ab der konfigurierten
Mindestanzahl von Bewertungen (standardmäßig drei). Für je 100 erstmals
bewertete unterschiedliche Cover wird einmalig 1 Baxx vergeben. Änderungen,
Löschen und erneutes Bewerten desselben Covers erhöhen diesen Lebenszeitstand
nicht.

Cover werden über die MediaWiki-API des Maddraxikons ermittelt, geprüft, in
zwei WebP-Größen umgewandelt und ausschließlich im privaten Laravel-Storage
gespeichert. Die Anwendung liefert sie über eine autorisierte Mitgliederroute
aus; ein Hotlink zum Maddraxikon wird nicht verwendet.

Für einen kontrollierten Rollout bleiben beide Feature-Flags zunächst aus:

```bash
php artisan migrate --force
php artisan db:seed --class=BaxxEarningRuleSeeder --force
php artisan maddraxikon:map-review-books --dry-run --all
php artisan maddraxikon:map-review-books --all
php artisan cover-ratings:sync-covers --dry-run
```

Danach `COVER_RATINGS_SYNC_ENABLED=true` setzen, Konfiguration cachen und den
ersten echten Abgleich ausführen. Nach Prüfung der Bilder wird
`COVER_RATINGS_ENABLED=true` aktiviert:

```bash
php artisan config:cache
php artisan cover-ratings:sync-covers
```

Der Befehl unterstützt gezielte Läufe mit `--book=<lokale-id>` und
`--series=maddrax|hardcovers|missionmars|volkdertiefe|2012|abenteurer`.
`--force` ist bewusst erforderlich, wenn eine bereits bewertete Ausgabe im
Maddraxikon auf eine andere Titelbilddatei umgestellt wurde.

Die wichtigsten Umgebungsvariablen sind:

```env
COVER_RATINGS_ENABLED=false
COVER_RATINGS_SYNC_ENABLED=false
COVER_RATINGS_RESULTS_MIN_VOTES=3
COVER_RATINGS_SYNC_INTERVAL_HOURS=24
COVER_RATINGS_ALLOWED_MEDIA_ORIGINS=https://de.maddraxikon.com
COVER_RATINGS_IMAGE_DISK=private
```

Das Synchronisationsintervall akzeptiert ausschließlich echte Teiler eines
Tages: `1`, `2`, `3`, `4`, `6`, `8`, `12` oder `24`. Andere Werte fallen aus
Sicherheitsgründen auf den täglichen Lauf zurück.

## Maddrax-Fantreffen 2026 Event-System

Das Anmeldesystem für das Maddrax-Fantreffen am 9. Mai 2026 bietet:

### Funktionen

- **Öffentliche Event-Seite** (`/maddrax-fantreffen-2026`) mit allen Veranstaltungsdetails
- **Anmeldeformular** mit unterschiedlichen Feldern für Mitglieder und Gäste
- **T-Shirt-Bestellung** mit Größenauswahl und Deadline-Tracking (28.02.2026)
- **Automatische E-Mail-Benachrichtigungen** bei neuen Anmeldungen
- **PayPal-Integration** über PayPal.me-Links (Freunde & Familie)
- **Zahlungsbestätigungsseite** mit Session-Protection für Gäste
- **Admin-Dashboard** (`/admin/fantreffen-2026`) für Vorstand und Kassenwart mit:
  - Statistiken (Teilnehmer, T-Shirts, ausstehende Zahlungen)
  - Filterbare Anmeldungsliste (Mitgliedsstatus, T-Shirt, Zahlung)
  - Toggle-Buttons für Zahlungseingang und T-Shirt-Status
  - CSV-Export aller Anmeldungen

### Konfiguration

Fügen Sie folgende Umgebungsvariablen zur `.env` hinzu:

```env
PAYPAL_ME_USERNAME=OfficialMaddraxFanclub
PAYPAL_FANTREFFEN_EMAIL=vorstand@maddrax-fanclub.de
```

### Preisstruktur

- Mitglieder: Kostenlos (nur Event-Teilnahme)
- Gäste: 5,00 €
- Event-T-Shirt: 25,00 € (für alle)

### Zugriffskontrolle

Das Admin-Dashboard ist nur für Benutzer mit den Rollen `Admin`, `Vorstand` oder `Kassenwart` zugänglich. Die Middleware `EnsureVorstandOrKassenwart` regelt den Zugriff.

## Kompendium-Suche: lexikal und hybrid

Die Typesense-Suche bleibt standardmäßig rein lexikal. Laravel Scout 11.7 kann
optional die native Typesense-Einbettung für eine hybride Volltext-/Semantiksuche
nutzen. Suchmodus und aktive Indexvariante sind absichtlich getrennt: Der Modus
steuert nur die Anfrage, die Variante dagegen Collection-Name und Schema.

```env
KOMPENDIUM_SEARCH_MODE=lexical
KOMPENDIUM_SEARCH_INDEX_VARIANT=lexical
KOMPENDIUM_SEARCH_INDEX_VERSION=1
KOMPENDIUM_SEARCH_EMBEDDING_MODEL=ts/multilingual-e5-large
KOMPENDIUM_SEARCH_TEXT_WEIGHT=1
KOMPENDIUM_SEARCH_SEMANTIC_WEIGHT=2
```

Vor einem Rollout werden RAM-Bedarf und Ergebnisqualität in einer
Staging-Umgebung geprüft. Für den kontrollierten Aufbau werden Suchzugriffe und
schreibende Index-Jobs in einem Wartungsfenster pausiert. Der Suchmodus bleibt
zunächst `lexical`, während `KOMPENDIUM_SEARCH_INDEX_VARIANT=hybrid` gesetzt
wird. Anschließend die Konfiguration leeren und den neuen Index vollständig
aufbauen:

```bash
php artisan config:clear
php artisan romane:index --fresh
```

Nach erfolgreicher Prüfung der lexikalischen Suche auf dieser Collection werden
Suchzugriffe und Index-Jobs wieder freigegeben. Erst dann wird
`KOMPENDIUM_SEARCH_MODE=hybrid` aktiviert. Ab diesem Zeitpunkt müssen reguläre
Indexierungs- und Löschvorgänge weiter auf dieselbe aktive Hybrid-Collection
zeigen.

Phrasen, `OR`, `NOT` und Ausschlüsse bleiben absichtlich lexikal, damit deren
bestehende Semantik erhalten bleibt. `KOMPENDIUM_SEARCH_MODE=lexical` ist der
sofortige Kill-Switch: Er deaktiviert nur die Vektorsuche und fragt dieselbe,
weiterhin aktuell gehaltene Hybrid-Collection lexikalisch ab. Dabei darf
`KOMPENDIUM_SEARCH_INDEX_VARIANT` nicht zurück auf `lexical` gestellt werden,
denn die alte Collection ist nach der Migration nur noch ein unveränderlicher
Snapshot und kein synchrones Sofort-Fallback. Ein Schema-Rollback benötigt einen
kontrolliert neu aufgebauten beziehungsweise synchronisierten Zielindex (oder
künftig einen atomaren Typesense-Alias-Wechsel). Suchmodus und Laufzeit werden
ohne zusätzliche personenbezogene Daten im Suchprotokoll erfasst.

## Abhängigkeiten und Supply-Chain-Prüfungen

Die Lockfiles sind verbindlich. Vor einem Merge von Dependency-Updates laufen
mindestens folgende Prüfungen:

```bash
composer validate --strict
composer audit --locked --abandoned=fail
composer check-platform-reqs --lock
npm ci
npm install-scripts ls
npm audit --package-lock-only --audit-level=moderate
```

`npm audit signatures` läuft in CI zusätzlich als Best-Effort-Prüfung. Der
öffentliche Registry-Endpunkt liefert für einzelne Pakete ohne Attestation
derzeit `404`; dieser externe Metadatenfehler darf die übrigen reproduzierbaren
Sicherheitsprüfungen nicht verdecken.

Composer blockiert Advisories, aufgegebene Pakete und Malware bereits während
der Auflösung. Dependabot verzögert normale Patch-, Minor- und Major-Releases
gestaffelt, während Security-Updates von diesem Cooldown unberührt bleiben.
Container-Basis- und Service-Images sind versions- und digestgenau gepinnt und
werden zusätzlich wöchentlich gescannt.

## Tests & Qualitätssicherung

| Zweck                        | Befehl |
|------------------------------|--------|
| Vollständige Pest-Suite      | `composer test` |
| Tests zweimal auf Instabilität prüfen | `composer test:stability:repeat` |
| Fehlgeschlagene Tests einmal diagnostisch wiederholen | `composer test:stability:retry` |
| PHP-Tests im Docker-Stack    | `npm run docker:dev:test:php` |
| Frische TIA-Basis aufzeichnen | `composer test:tia:fresh` |
| Nur betroffene Tests mit TIA | `composer test:tia` |
| Abgedeckte Mutationen prüfen | `composer test:mutate` |
| Pest-PHPStan (migrierte Tests) | `composer test:types` |
| Pest-Rector-Migration prüfen | `composer test:rector` |
| Architektur- und Security-Regeln | `composer test:arch` |
| Profanity-Scan               | `composer test:profanity` |
| Pest-Browser-Regression      | `./vendor/bin/pest tests/Browser/ModalBackdropPreviewTest.php` |
| JavaScript-Tests (Vitest)    | `npm run docker:dev:test:js` |
| Komponenten-Tests (Vitest im Docker-Container) | `npm run docker:dev:test:vitest` |
| End-to-End-Checks mit Docker-PHP 8.5 | `npm run test:e2e:docker` |
| Modal-Screenshot-Export mit Docker | `npm run test:e2e:modal-screenshots:docker` |
| Code-Style (Laravel Pint)    | `./vendor/bin/pint` |

Die schnellen Standard-Checks laufen lokal bewusst effizient: Pest bleibt auf SQLite `:memory:`, Vitest läuft im Node-Container, und die Runtime selbst bleibt parallel produktionsnah über MariaDB, Typesense, Nginx und Queue. TIA und Mutation Testing benötigen Xdebug im Coverage-Modus; die Composer-Skripte aktivieren diesen Modus automatisch. Der Mutation-Job prüft die ausdrücklich mit `mutates()` markierten Sicherheitsklassen und erzwingt für die derzeit 137 Mutationen einen Score von 100 %. Der explizite Anwendungspfad umgeht außerdem eine Pfadauflösungsschwäche von Pest 5.2 unter Windows. Da Pest 5 TIA bei expliziten Testpfaden deaktiviert und keine PHPUnit-Testklassen unterstützt, verwenden diese Skripte `phpunit.tia.xml` mit ausschließlich funktionalen Pest-Tests. Eine frische Baseline wird auf `main` zusätzlich als GitHub-Actions-Artefakt veröffentlicht; Mutation Tests laufen separat wöchentlich und manuell.
Die PHPUnit-13.3-Diagnosen `test:stability:repeat` und `test:stability:retry` sind bewusst manuelle Zusatzprüfungen. Insbesondere Retry ersetzt keinen regulär erfolgreichen Testlauf und wird deshalb nicht als CI-Pflichtprüfung verwendet.
Die Playwright-Suite nutzt mit `npm run test:e2e:docker` standardmäßig den `playwright-php`-Service aus `docker-compose.dev.yml` und startet damit einen isolierten PHP-8.5-Container mit SQLite-Support für die Browser-Suite.
Der Export der Modal-Vorschau-Screenshots ist bewusst an `PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS=1` gekoppelt; das Docker-Skript `npm run test:e2e:modal-screenshots:docker` setzt diese Flag automatisch, während normale CI- und lokale Playwright-Läufe keine dauerhaften Screenshot-Artefakte erzeugen.

Externe Test- oder Sandbox-Credentials gehören ausschließlich in `.env.docker.dev.local` und niemals in versionierte Dateien.

Der Test-Stack verwendet Pest 5.2 und PHPUnit 13.3. Alle direkt eingebundenen Pest-Plugins sind auf `^5.0` festgelegt; PHP 8.5 erfüllt die Mindestanforderung von Pest 5 (PHP 8.4). Das Pest-Agent-Plugin darf ausschließlich lokal auf einem geprüften Arbeitsbaum ohne Produktions-Credentials verwendet werden; automatisch erzeugte Änderungen werden wie Fremdcode geprüft und durch die normalen Tests abgesichert. Weitere Hintergründe stehen im [Pest-5-Implementierungsplan](PEST_5_IMPLEMENTIERUNGSPLAN.md).

## Deployment

Für das Deployment steht ein mehrstufiger Dockerfile bereit:

1. **Node-Build-Stage** kompiliert die Vite-Assets mit Node 26.9 und npm 12.0.2 (`npm ci` + `npm run build`).
2. **Gemeinsame PHP-Basis** installiert die produktions- und testrelevanten PHP-Extensions.
3. **Production-Target** installiert Composer-Abhängigkeiten ohne Dev-Pakete, kopiert die Anwendung sowie die vorgerenderten Assets und setzt korrekte Dateiberechtigungen.
4. **Development-Target** installiert zusätzlich Dev-Abhängigkeiten und dient als Basis für `docker-compose.dev.yml`.

Bei klassischen Deployments sollten Sie mindestens folgende Schritte automatisieren:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Stellen Sie sicher, dass `APP_URL` in der `.env` auf die öffentlich erreichbare URL zeigt und dass ein Queue-Worker für zeitkritische Prozesse aktiv ist.

Der GitHub-Deployment-Workflow nutzt ab Laravel 13.25 den globalen
Queue-Pause-Mechanismus. Vor dem Containerwechsel nimmt der Worker keine neuen
Jobs mehr an und wird mit einem großzügigen Timeout beendet; vor dem Start der
neuen Worker hebt der Workflow die Pause garantiert wieder auf. Beim ersten
Deployment von einer älteren Laravel-Version wird die Pause per Feature-Check
übersprungen.

Nach erfolgreich abgeschlossenen Healthchecks entfernt der Workflow nur
unreferenzierte Docker-Images, die älter als sieben Tage sind. So bleibt ein
kurzer lokaler Rollback-Puffer erhalten, während alte `latest`-Versionen nicht
dauerhaft Speicherplatz auf dem Produktionsserver belegen. Docker-Volumes und
damit Datenbank- oder Anwendungsdaten werden dabei nicht bereinigt.

## Nützliche Artisan-Befehle

| Zweck | Befehl |
|-------|--------|
| Server starten | `php artisan serve` |
| Datenbankmigrationen ausführen | `php artisan migrate` |
| Migrationen rückgängig machen | `php artisan migrate:rollback` |
| Datenbank frisch aufsetzen | `php artisan migrate:fresh` |
| Route-Cache leeren | `php artisan route:clear` |
| Application-Cache leeren | `php artisan cache:clear` |
| Romane indexieren | `php artisan romane:index` |
| Romane neu indexieren | `php artisan romane:index --fresh` |
| Romane, Hardcover, Mission Mars, Das Volk der Tiefe, 2012 & Die Abenteurer importieren | `php artisan books:import` |
| Rezensionen importieren | `php artisan reviews:import-old --fresh` |
| Alle Serien crawlen (Maddrax, Hardcover, Mission Mars, 2012, Das Volk der Tiefe, Die Abenteurer) | `php artisan crawlnovels` |
| "Mission Mars"-Romane crawlen | `php artisan crawlmissionmars` |
| "2012"-Mini-Serie crawlen | `php artisan crawl2012` |
| "Das Volk der Tiefe"-Romane crawlen | `php artisan crawlvolkdertiefe` |
| "Die Abenteurer"-Romane crawlen | `php artisan crawlabenteurer` |
| Hardcover crawlen | `php artisan crawlhardcovers` |
| Sitemap generieren | `php artisan sitemap:generate` |
| Mitgliederkarte aktualisieren | `php artisan member-map:refresh` |
| Alle Queues kontrolliert pausieren | `php artisan queue:pause --all` |
| Globale Queue-Pause aufheben | `php artisan queue:resume --all` |

Weitere Befehle stehen über `php artisan list` zur Verfügung.

### Sitemap erzeugen

Die Sitemap aller öffentlichen Seiten lässt sich mit folgendem Kommando aktualisieren:

```bash
php artisan sitemap:generate
```

Die Datei wird unter `public/sitemap.xml` gespeichert. Aktualisieren Sie die Sitemap regelmäßig und stellen Sie sicher, dass `APP_URL` korrekt gesetzt ist, damit absolute URLs generiert werden.

### Scheduler in Produktion

Im produktiven Docker-Deployment ist der Compose-Dienst `scheduler` mit
`php artisan schedule:work` der verbindliche Scheduler. Der Compose-Dienst
`queue` arbeitet die dabei erzeugten Queue-Jobs ab. Es darf nicht zusätzlich
ein Host-Cronjob mit `schedule:run` oder ein zweiter, abweichend konfigurierter
Queue-Worker aktiv sein.

Nach jedem Deployment muss die Anwendung selbst – nicht nur der laufende
Container – geprüft werden:

```bash
docker compose --env-file .env.production exec -T app php artisan schedule:list
docker compose --env-file .env.production exec -T app php artisan maddraxikon:status --skip-api
docker compose --env-file .env.production logs --tail=100 scheduler queue
```

`schedule:list` muss insbesondere `maddraxikon:scheduler-heartbeat`,
`maddraxikon:sync-job`, `maddraxikon:evaluate-job` und
`maddraxikon:review-ratings-sync-job` enthalten. Meldet der
Status `Recovery nötig: ja`, darf der Rückstand nicht mit einem erzwungenen
normalen Sync übersprungen werden. In diesem Fall ist das gemeldete Zeitfenster
zu prüfen und `maddraxikon:recover` erst anschließend bewusst freizugeben.

Bei Installationen ohne den Compose-Scheduler kann alternativ genau ein
minütlicher Cronjob verwendet werden:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

## Support

Fragen, Feature-Wünsche oder Bug-Meldungen können über das [GitHub-Issue-Tracking](https://github.com/McNamara84/omxfc-vereinswebseite/issues) oder per Mail an [info@maddraxikon.com](mailto:info@maddraxikon.com) gestellt werden.

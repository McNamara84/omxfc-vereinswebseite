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
  - [Maddraxikon-Baxx-Regeln verwalten](#maddraxikon-baxx-regeln-verwalten)
  - [Maddraxikon-Bewertungen in Rezensionen](#maddraxikon-bewertungen-in-rezensionen)
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
- **Meeting- und Kassenbuchmodule** zur Organisation von Vereinstreffen und Finanzverwaltung.
- **Maddraxiversum-Minispiele** und weitere Community-Features (Rezensionen, Romantausch, Hörbücher).

## Technologie-Stack

- **Backend:** Laravel 13, Jetstream, Sanctum, Scout (TNTSearch), Livewire 4, Spatie PDF & Sitemap.
- **Frontend:** Tailwind CSS, Alpine.js, Vite, Chart.js, Simple Datatables, Leaflet.
- **Testing:** PHPUnit 13, Vitest 4, Playwright inkl. axe-core für Accessibility-Regressionen.
- **Tooling & DevOps:** Laravel Pint, Dockerfile mit Production- und Development-Target, docker-compose.dev.yml für den lokalen Stack.

## Voraussetzungen

| Komponente       | Version / Hinweis                                      |
|------------------|---------------------------------------------------------|
| Docker Desktop / Docker Engine | Empfohlen für die lokale Entwicklung mit `docker-compose.dev.yml` |
| PHP              | ≥ 8.5 inklusive Extensions: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `bcmath`, `gd`, `pcntl` |
| Composer         | ≥ 2.6, nur für klassische Host-Entwicklung nötig        |
| Node.js & npm    | Node 26 (Single Source of Truth: `.node-version`), nur für klassische Host-Entwicklung nötig |
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
  npm install
   ```
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
Anwendung liest dazu ausschließlich `vote.vote_user_id` sowie die Tabellen
`page` und `redirect` über die separate Verbindung `maddraxikon` und speichert
lokal einen höchstens 60 Minuten sichtbaren Snapshot. Ein vorhandenes
MediaWiki-Tabellenpräfix wird über `MADDRAXIKON_DB_PREFIX` konfiguriert. Der
Datenbankbenutzer muss auf diese Tabellen beschränkte `SELECT`-Rechte besitzen.

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
aktiviert. Das Trennen eines Kontos oder Deaktivieren des Flags blendet die
Bewertung sofort aus; der 15-minütige Queue-Job bereinigt den Snapshot danach.

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

## Tests & Qualitätssicherung

| Zweck                        | Befehl |
|------------------------------|--------|
| Vollständige Pest-Suite      | `composer test` |
| PHP-Tests im Docker-Stack    | `npm run docker:dev:test:php` |
| Frische TIA-Basis aufzeichnen | `composer test:tia:fresh` |
| Nur betroffene Tests mit TIA | `composer test:tia` |
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

Die schnellen Standard-Checks laufen lokal bewusst effizient: Pest bleibt auf SQLite `:memory:`, Vitest läuft im Node-Container, und die Runtime selbst bleibt parallel produktionsnah über MariaDB, Typesense, Nginx und Queue. TIA benötigt Xdebug im Coverage-Modus; die Composer-Skripte aktivieren diesen Modus automatisch. Da Pest 5 TIA bei expliziten Testpfaden deaktiviert und keine PHPUnit-Testklassen unterstützt, verwenden diese Skripte `phpunit.tia.xml` mit ausschließlich funktionalen Pest-Tests. Eine frische Baseline wird auf `main` zusätzlich als GitHub-Actions-Artefakt veröffentlicht.
Die Playwright-Suite nutzt mit `npm run test:e2e:docker` standardmäßig den `playwright-php`-Service aus `docker-compose.dev.yml` und startet damit einen isolierten PHP-8.5-Container mit SQLite-Support für die Browser-Suite.
Der Export der Modal-Vorschau-Screenshots ist bewusst an `PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS=1` gekoppelt; das Docker-Skript `npm run test:e2e:modal-screenshots:docker` setzt diese Flag automatisch, während normale CI- und lokale Playwright-Läufe keine dauerhaften Screenshot-Artefakte erzeugen.

Externe Test- oder Sandbox-Credentials gehören ausschließlich in `.env.docker.dev.local` und niemals in versionierte Dateien.

Der Test-Stack verwendet die stabilen Versionen Pest 5.0.2 und PHPUnit 13.2.6. Alle direkt eingebundenen Pest-Plugins sind auf `^5.0` festgelegt; PHP 8.5 erfüllt die Mindestanforderung von Pest 5 (PHP 8.4). TIA, neue Format-Expectations, PHPStan, Rector, das Agent-Plugin, erweiterte Architekturregeln und zeitbasiertes CI-Sharding sind eingeführt. Umsetzungsstand, Ausbaupfade und der derzeitige Upstream-Blocker für Mutation Testing stehen im [Pest-5-Implementierungsplan](PEST_5_IMPLEMENTIERUNGSPLAN.md).

## Deployment

Für das Deployment steht ein mehrstufiger Dockerfile bereit:

1. **Node-Build-Stage** kompiliert die Vite-Assets mit Node 26 (`npm ci` + `npm run build`).
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

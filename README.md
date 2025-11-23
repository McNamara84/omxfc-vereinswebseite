# OMFXC Vereinswebseite

![Laravel 12](https://img.shields.io/badge/laravel-12-red?logo=laravel&style=flat)
![PHP 8.2](https://img.shields.io/badge/php-8.2-blue?logo=php)
![Node 20](https://img.shields.io/badge/node-20-5FA04E?logo=node.js&logoColor=white)
![JS Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/js-coverage.svg)
![PHP Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/php-coverage.svg)
[![License](https://img.shields.io/badge/license-GPLv3-green)](LICENSE)
[![E2E Tests](https://github.com/McNamara84/omxfc-vereinswebseite/actions/workflows/playwright.yml/badge.svg)](https://github.com/McNamara84/omxfc-vereinswebseite/actions/workflows/playwright.yml)

Offizielle Laravel-12-Anwendung für die Vereinswebseite des **Offizieller MADDRAX Fanclub (OMFXC)**. Das Projekt kombiniert eine moderne, barrierearme Oberfläche auf Basis von Tailwind CSS, Alpine.js und Livewire mit einem umfassenden Funktionsumfang für Mitgliederverwaltung, Vereinskommunikation und Content-Pflege.

## Inhaltsverzeichnis

- [Hauptfunktionen](#hauptfunktionen)
- [Technologie-Stack](#technologie-stack)
- [Voraussetzungen](#voraussetzungen)
- [Lokale Entwicklung](#lokale-entwicklung)
  - [Installation](#installation)
  - [Entwicklungsumgebung starten](#entwicklungsumgebung-starten)
  - [Datenbank seeden](#datenbank-seeden)
- [Tests & Qualitätssicherung](#tests--qualitätssicherung)
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

- **Backend:** Laravel 12, Jetstream, Sanctum, Scout (TNTSearch), Livewire 3, Spatie PDF & Sitemap.
- **Frontend:** Tailwind CSS, Alpine.js, Vite, Chart.js, Simple Datatables, Leaflet.
- **Testing:** PHPUnit 11, Jest 30, Vitest 3, Playwright inkl. axe-core für Accessibility-Regressionen.
- **Tooling & DevOps:** Laravel Pint, Laravel Sail (optional), Dockerfile für PHP-FPM + Node 20 Build-Stage.

## Voraussetzungen

| Komponente       | Version / Hinweis                                      |
|------------------|---------------------------------------------------------|
| PHP              | ≥ 8.2 inklusive Extensions: `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `pcntl` |
| Composer         | ≥ 2.6                                                   |
| Node.js & npm    | Node 20 LTS, npm 10                                     |
| Datenbank        | MariaDB / MySQL (Standard) oder SQLite für lokale Tests |
| Optional         | Docker & Docker Compose, falls Container genutzt werden |

> **Tipp:** Für eine schnelle lokale Entwicklungsumgebung kann auch Laravel Sail genutzt werden. Die Standardkonfiguration erwartet jedoch eine klassische LAMP-/LEMP-Umgebung.

## Lokale Entwicklung

### Installation

1. Repository klonen und ins Projektverzeichnis wechseln.
2. PHP- und Node-Abhängigkeiten installieren:
   ```bash
   composer install
   npm install
   ```
3. Beispiel-Environment kopieren und Applikationsschlüssel erzeugen:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Datenbankzugang in `.env` anpassen (z. B. `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
5. Datenbankmigrationen ausführen:
   ```bash
   php artisan migrate
   ```
6. Assets für Produktion kompilieren (optional, Vite-Dev-Server reicht lokal):
   ```bash
   npm run build
   ```

### Entwicklungsumgebung starten

- **Vite- und PHP-Server separat:**
  ```bash
  php artisan serve
  npm run dev
  ```
- **Kombiniert mit Queue-Worker (empfohlen):**
  ```bash
  composer run dev
  ```

Der kombinierte Befehl startet den PHP-Entwicklungsserver, einen `queue:work`-Prozess sowie den Vite-Dev-Server parallel. Stellen Sie sicher, dass notwendige externe Dienste (z. B. Datenbank, Redis) laufen.

### Datenbank seeden

Für Demodaten stehen Seeder im Ordner `database/seeders` zur Verfügung. Sie lassen sich einzeln oder gesammelt ausführen:

```bash
php artisan db:seed --class=DefaultAdminAndTeamSeeder
php artisan db:seed --class=TodoCategorySeeder
php artisan db:seed
```

Spezielle Seeder wie `TodoPlaywrightSeeder` und `FantreffenPlaywrightSeeder` bereiten End-to-End-Tests vor und sollten nur in Testumgebungen ausgeführt werden.

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
| PHPUnit-Tests                | `php artisan test` |
| JavaScript-Tests (Jest)      | `npm run test` |
| Komponenten-Tests (Vitest)   | `npm run test:vitest` |
| End-to-End- & Accessibility-Checks | `npm run test:e2e` |
| Code-Style (Laravel Pint)    | `./vendor/bin/pint` |

Die Playwright-Suite setzt eine laufende Anwendung (lokal oder in CI) voraus und führt zusätzlich axe-core-Prüfungen für Barrierefreiheit durch.

## Deployment

Für das Deployment steht ein mehrstufiger Dockerfile bereit:

1. **Node-Build-Stage** kompiliert die Vite-Assets mit Node 20 (`npm ci` + `npm run build`).
2. **PHP-FPM-Stage** installiert Composer-Abhängigkeiten ohne Dev-Pakete, kopiert die Anwendung sowie die vorgerenderten Assets und setzt korrekte Dateiberechtigungen.

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
| Romane, Hardcover, Mission Mars & Das Volk der Tiefe importieren | `php artisan books:import` |
| Rezensionen importieren | `php artisan reviews:import-old --fresh` |
| Neue Romane & Hardcover crawlen | `php artisan crawlnovels` |
| "Das Volk der Tiefe"-Romane crawlen | `php artisan crawlvolkdertiefe` |
| Sitemap generieren | `php artisan sitemap:generate` |

Weitere Befehle stehen über `php artisan list` zur Verfügung.

### Sitemap erzeugen

Die Sitemap aller öffentlichen Seiten lässt sich mit folgendem Kommando aktualisieren:

```bash
php artisan sitemap:generate
```

Die Datei wird unter `public/sitemap.xml` gespeichert. Aktualisieren Sie die Sitemap regelmäßig und stellen Sie sicher, dass `APP_URL` korrekt gesetzt ist, damit absolute URLs generiert werden.

### Scheduler in Produktion

Der Laravel-Scheduler sollte auf Produktionssystemen jede Minute aufgerufen werden, um Hintergrundaufgaben (u. a. `member-map:refresh`) auszuführen:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

## Support

Fragen, Feature-Wünsche oder Bug-Meldungen können über das [GitHub-Issue-Tracking](https://github.com/McNamara84/omxfc-vereinswebseite/issues) oder per Mail an [info@maddraxikon.com](mailto:info@maddraxikon.com) gestellt werden.

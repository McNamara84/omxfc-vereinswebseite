# OMFXC Vereinswebseite

![Laravel 12](https://img.shields.io/badge/laravel-12-red?logo=laravel&style=flat)
![PHP 8.4](https://img.shields.io/badge/php-%5E8.2-blue?logo=php)
[![Build & Deploy](https://github.com/mcnamara84/omxfc-vereinswebseite/actions/workflows/deploy.yml/badge.svg?branch=main)](https://github.com/mcnamara84/omxfc-vereinswebseite/actions/workflows/deploy.yml)
![JS Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/js-coverage.svg)
![PHP Coverage](https://raw.githubusercontent.com/McNamara84/omxfc-vereinswebseite/image-data/php-coverage.svg)
[![License](https://img.shields.io/badge/license-GPLv3-green)](https://github.com/mcnamara84/omxfc-vereinswebseite/blob/main/LICENSE)

Eine Laravel 12 Anwendung für die Vereinswebseite des OMFXC.

## Versionsanzeige

Die im Footer angezeigte Versionsnummer wird automatisch aus dem neuesten Git-Tag des Repositories ermittelt.

## Installation

1. Repository clonen und ins Projektverzeichnis wechseln.
2. Abhängigkeiten installieren:
   ```bash
   composer install
   npm install
   ```
3. Umgebungsdatei kopieren und App-Schlüssel generieren:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Frontend Assets bauen:
   ```bash
   npm run build
   ```
5. Datenbank einrichten und Migrationen ausführen:
   ```bash
   php artisan migrate
   ```
6. Entwicklungsserver starten:
   ```bash
   php artisan serve
   ```

## Wichtige Artisan Befehle

| Zweck | Befehl |
|-------|--------|
| Docker Compose nutzen | docker exec maddrax-app php
| Server starten | `php artisan serve` |
| Datenbankmigrationen ausführen | `php artisan migrate` |
| Migrationen rückgängig machen | `php artisan migrate:rollback` |
| Komplettes Zurücksetzen der Datenbank | `php artisan migrate:fresh` |
| Route-Cache leeren | `php artisan route:clear` |
| Application Cache leeren | `php artisan cache:clear` |
| Romane indexieren | `php artisan romane:index` |
| Romane komplett neu indexieren | `php artisan romane:index --fresh` |
| Romane, Hardcover & Mission Mars-Heftromane importieren | `php artisan books:import` |
| Rezensionen importieren | `php artisan reviews:import-old --fresh` |
| Neue Romane & Hardcover crawlen | `php artisan crawlnovels` |
| Sitemap generieren | `php artisan sitemap:generate` |

Weitere Befehle lassen sich über `php artisan list` anzeigen.

## Sitemap

Die Sitemap der öffentlichen Seiten kann mit folgendem Befehl erstellt werden:

```bash
php artisan sitemap:generate
```

Die Datei wird unter `public/sitemap.xml` abgelegt und sollte regelmäßig aktualisiert werden.

Stellen Sie sicher, dass `APP_URL` in der `.env`-Datei auf eine gültige, öffentlich erreichbare URL gesetzt ist.

## Scheduler in Produktion

Damit geplante Aufgaben ausgeführt werden können, muss auf dem Produktionsserver der Laravel Scheduler laufen.
Richten Sie dazu einen Cronjob ein, der den Scheduler jede Minute aufruft:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Der Scheduler führt stündlich das Kommando `member-map:refresh` aus und aktualisiert so den Cache der Mitgliederkarte.


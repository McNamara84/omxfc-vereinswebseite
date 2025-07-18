# OMFXC Vereinswebseite

[![Build & Deploy](https://github.com/mcnamara84/omxfc-vereinswebseite/actions/workflows/deploy.yml/badge.svg?branch=main)](https://github.com/mcnamara84/omxfc-vereinswebseite/actions/workflows/deploy.yml)
[![PHP Version](https://img.shields.io/badge/php-8.2-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/mcnamara84/omxfc-vereinswebseite/blob/main/LICENSE)

Eine Laravel 12 Anwendung für die Vereinswebseite des OMFXC.

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
| Server starten | `php artisan serve` |
| Datenbankmigrationen ausführen | `php artisan migrate` |
| Migrationen rückgängig machen | `php artisan migrate:rollback` |
| Komplettes Zurücksetzen der Datenbank | `php artisan migrate:fresh` |
| Route-Cache leeren | `php artisan route:clear` |
| Application Cache leeren | `php artisan cache:clear` |
| Romane indexieren | `php artisan romane:index` |
| Romane komplett neu indexieren | `php artisan romane:index --fresh` |
| Romane importieren | `php artisan books:import` |
| Rezensionen importieren | `php artisan reviews:import-old --fresh` |

Weitere Befehle lassen sich über `php artisan list` anzeigen.


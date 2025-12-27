Kurz und prägnant: Hinweise für AI-Coding-Agenten, um hier sofort produktiv zu werden.

Ziel: Liefere kleine, sichere Code-Änderungen, Tests oder Dokument-Updates, die zur Laravel-12-basierten Vereinswebseite passen.

1) Grosser Architekturüberblick
- Backend: Laravel 12 (PHP ≥ 8.5). App-Logik liegt unter `app/` (Controller, Livewire-Komponenten in `app/Livewire`, Services in `app/Services`, Jobs in `app/Jobs`).
- Frontend: Vite + Tailwind + Alpine.js + Livewire. Assets in `resources/`; Vite-Konfiguration in `vite.config.js`.
- Daten: MySQL/MariaDB in Produktion; Tests/CI verwenden SQLite (siehe `phpunit.xml`).
- Workflows: CI definiert in `.github/workflows/*` — `phpunit.yml`, `playwright.yml`, `vitest.yml`. Asset-Builder ist `.github/actions/build-assets/action.yml`.

2) Wichtige Dateien/Orte (schnelle Referenz)
- Projekt-README: `README.md` — enthält Setup-, Dev- & Deploy-Hinweise.
- Composer/Node-Manifeste: `composer.json`, `package.json` (Node 24 LTS, PHP 8.5 Mindestanforderung). Die Node-Version wird zentral in `.node-version` gepflegt.
- Tests: `phpunit.xml`, `tests/TestCase.php` (beachtet HTTP-Fakes & automatische Seeding).
- Routen: `routes/web.php` (Controller-zentrierte Struktur, deutsche Route-/Methodennamen).
- Artisan entrypoint: `artisan` (standard Laravel CLI).

3) Entwicklungs- und Test-Workflows (konkrete Befehle)
- Abhängigkeiten installieren:
  - `composer install`
  - `npm install` (Node 24 LTS)
- Environment und DB:
  - `cp .env.example .env` und `php artisan key:generate`
  - Lokale Migration: `php artisan migrate`
- Dev-Server & Assets:
  - PHP + Vite getrennt: `php artisan serve` und `npm run dev`
  - Kombiniert (empfohlen beim Entwickeln): `composer run dev` — startet `php artisan serve`, `queue:work` und `npm run dev` parallel (siehe `composer.json` scripts).
- Tests:
  - PHPUnit: `php artisan test` (CI/`phpunit.xml` nutzt SQLite in-memory or sqlite file)
  - JS-Unit (Jest): `npm run test`
  - Vitest: `npm run test:vitest`
  - Playwright E2E (+axe accessibility): `npm run test:e2e` (Playwright benötigt gebaute Assets + DB migrations)

4) CI / E2E Hinweise
- Playwright CI (`.github/workflows/playwright.yml`) baut Assets, migriert die Datenbank (SQLite) und führt E2E-Browser-Tests. Für lokale E2E: `php artisan migrate --force`, `npm run build`, dann `npx playwright test`.
- PHP-Tests in CI installieren Composer-Abhängigkeiten, erzeugen eine `database/database.sqlite` und nutzen `php artisan test`.

5) Projekt-spezifische Konventionen & Fallen
- Tests erwarten HTTP-Fakes für externe Dienste (z. B. Nominatim). `tests/TestCase.php` faked Anfragen an `nominatim.openstreetmap.org` und seedet die DB automatisch in setUp(). Verändere das nicht ohne Grund.
- Tests/CI nutzen SQLite (phpunit.xml setzt DB_CONNECTION=sqlite und DB_DATABASE=:memory:); Code, der nur mit MySQL-Spezifika funktioniert, kann in CI fehlschlagen.
- Route-/Controller-Namen sind deutschsprachig; suche nach deutschen Schlüsselwörtern (z. B. `mitglieder`, `hoerbuecher`, `fantreffen`) bei Änderungen an Routen oder Views.
- Assets: Vite (Node 24 LTS) — fehlerhafte Node-Versionen sind eine häufige lokale Fehlerquelle.
- Dateiberechtigungen: CI setzt `chmod -R 777 storage bootstrap/cache` vor Tests; lokale Umgebungen müssen Schreibrechte für `storage`/`bootstrap/cache` erlauben.
- Rollen-System: Benutzer-Rollen (Admin, Vorstand, Kassenwart, Mitglied, etc.) werden über `team_user` Pivot-Tabelle gespeichert, nicht direkt am User-Model. Siehe `database/schema/sqlite-schema.sql` für Schema-Details.
- PayPal-Integration: Nutzt PayPal.me-Links (keine API), konfiguriert über `PAYPAL_ME_USERNAME` und `PAYPAL_FANTREFFEN_EMAIL` in `.env`.

6) Change/PR-Checks (was vor einem PR lokal ausführen)
- `composer install && npm ci`
- `php artisan migrate --force` (oder in Testkontext: `php artisan test` nutzt SQLite automatisch)
- `php artisan test` (Unit + Feature)
- `npm run test` und `npm run test:vitest` falls Frontend betroffen
- `npm run build` falls du Asset-Änderungen auslieferst

7) Concrete examples für AI-Aktionen
- Kleine Controller-Fix: Wenn du einen HTTP-Status falsch weitergibst, verändere die Methode in `app/Http/Controllers/*`, ergänze einen Feature-Test unter `tests/Feature` und laufe `php artisan test`.
- Frontend-Änderung an Komponenten: editiere `resources/js` / `resources/views`, baue Assets lokal (`npm run build`) und ergänze Vitest/Jest-Tests in `resources/js/tests`.
- Neue Background-Job: Erstelle Job unter `app/Jobs`, registriere ggf. in Scheduler (`app/Console/Kernel.php`) und füge einen Integrationstest, der `queue:work` nicht benötigt (synchron-mode in `phpunit.xml`).
- Event-System (Maddrax-Fantreffen): Livewire-Full-Page-Komponenten in `app/Livewire` (FantreffenAnmeldung, FantreffenAdminDashboard, FantreffenZahlungsbestaetigung), Model `app/Models/FantreffenAnmeldung.php`, Middleware `app/Http/Middleware/EnsureVorstandOrKassenwart.php` für Admin-Zugriff. PayPal-Config in `config/services.php`.

8) Was hier nicht tun / Vorsicht
- Keine geheimen Schlüssel in Änderungen (keine `.env` in commits).
- Keine Änderungen an CI-Secrets oder GitHub Actions ohne Rücksprache.

9) Referenzen (für schnelle Navigation)
- `README.md` — Setup & commands
- `composer.json`, `package.json` — runtime & scripts
- `phpunit.xml`, `tests/TestCase.php` — Testkontext
- `routes/web.php` — Routing-Patterns
- `.github/workflows/*` — CI expectations (phpunit, vitest, playwright)

Wenn etwas unklar ist oder du bevorzugte Prioritäten hast (z. B. Bugfix vs. neues Feature vs. Tests), sag kurz Bescheid — ich passe die Anleitung an bzw. erweitere Beispiele.

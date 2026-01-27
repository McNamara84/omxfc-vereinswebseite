# OMXFC Vereinswebseite – AI Agent Instructions

> MADDRAX-Fanclub-Website. Laravel 12 + Livewire 3 + Tailwind 4. Deutsche Domain-Sprache.

## Bevorzugte Technologien

- **PHP 8.5** – Nutze neueste Features: Property Hooks, `array_*` Funktionen, Pipe Operator, Asymmetric Visibility
- **Tailwind CSS 4** – Nutze moderne Syntax: `@theme`, CSS-Variablen, Container Queries, `@starting-style`
- **Livewire 4** – Nutze aktuelle Patterns: `#[Computed]`, `#[Locked]`, `wire:model.live`, Lazy Loading

## Architektur

| Layer | Stack | Pfad |
|-------|-------|------|
| Backend | Laravel 12, PHP 8.5, Livewire 4, Jetstream 5.3 | `app/` |
| Frontend | Vite 7, Tailwind 4, Alpine.js | `resources/` |
| Daten | MySQL/MariaDB (Prod), SQLite (Tests) | `database/` |
| CI | PHPUnit, Vitest 4, Playwright | `.github/workflows/` |

**Kern-Packages:** Jetstream (Teams/Auth), Scout+TNTSearch (Volltextsuche), Spatie PDF/Sitemap

**Kernstruktur:**
```
app/Http/Controllers/     # MVC-Controller (deutsche Methodennamen)
app/Livewire/             # Reaktive Full-Page-Komponenten
app/Services/             # Business-Logik: TeamPointService, MaddraxDataService, FantreffenDeadlineService
app/Enums/Role.php        # Anwaerter, Mitwirkender, Mitglied, Ehrenmitglied, Kassenwart, Vorstand, Admin
app/Models/               # Eloquent: User, Team, Todo, Review, FantreffenAnmeldung, KassenbuchEntry
```

## Entwicklungs-Workflow

```bash
# Setup
composer install && npm install      # Node 24 LTS (siehe .node-version)
cp .env.example .env && php artisan key:generate
php artisan migrate

# Entwickeln (empfohlen – startet serve + queue:work + vite parallel)
composer run dev

# Tests
php artisan test                      # PHPUnit (SQLite :memory:)
npm run test:vitest                   # Vitest (JS)
npm run test:e2e                      # Playwright (benötigt npm run build)
```

## Projekt-Konventionen

### Deutsche Domain-Sprache
Routes, Views und Models verwenden deutsche Begriffe:
- Routes: `/mitglieder`, `/hoerbuecher`, `/fantreffen`, `/kassenbuch`, `/romantausch`
- Models: `FantreffenAnmeldung`, `KassenbuchEntry`, `BookOffer`/`BookRequest`/`BookSwap`

### Rollen & Authorization
```php
// app/Enums/Role.php – Rollen über team_user Pivot
enum Role: string {
    case Anwaerter = 'Anwärter';
    case Vorstand = 'Vorstand';
    case Admin = 'Admin';
    // ...
}

// Middleware für geschützte Routes (routes/web.php)
Route::middleware(['vorstand-or-kassenwart'])->group(...);
Route::middleware(['admin'])->group(...);

// Policy-Pattern in Controllern
$this->authorize('update', $bookOffer);
```

### Livewire-Pattern
```php
// Full-Page-Komponenten mit Layout + Route::livewire()
Route::livewire('/admin/fantreffen-2026', FantreffenAdminDashboard::class)
    ->middleware('vorstand-or-kassenwart');

public function render() {
    return view('livewire.fantreffen-admin-dashboard')
        ->layout('layouts.app', ['title' => 'Admin Dashboard']);
}
```

### Service-Injection
```php
public function __construct(
    private readonly TeamPointService $teamPointService
) {}
```

### Test-Setup (`tests/TestCase.php`)
- HTTP-Fake für `nominatim.openstreetmap.org` ist automatisch aktiv (Geocoding-Mock)
- DB wird in `setUp()` automatisch geseeded – keine manuelle Seed-Logik nötig
- SQLite in-memory: **Kein MySQL-spezifischer Code** (z.B. `JSON_EXTRACT`) ohne SQLite-Fallback

## Häufige Tasks

| Aufgabe | Dateien | Test |
|---------|---------|------|
| Controller | `app/Http/Controllers/*` | `php artisan test --filter=ControllerName` |
| Livewire | `app/Livewire/`, `resources/views/livewire/` | `php artisan test` |
| Frontend/JS | `resources/js/`, Feature-spezifisch (z.B. `fantreffen.js`) | `npm run test:vitest && npm run build` |
| Neue Route | `routes/web.php` | Feature-Test unter `tests/Feature/` |
| Background-Job | `app/Jobs/` | Test mit `QUEUE_CONNECTION=sync` |

## Wichtige Pfade

- **Ausführliche AI-Doku:** `CLAUDE.md` (1300+ Zeilen Kontext)
- **Routing:** `routes/web.php` – Controller + `Route::livewire()`
- **Views nach Feature:** `resources/views/{fantreffen,kassenbuch,mitglieder,romantausch,hoerbuecher}/`
- **E2E-Seeder:** `TodoPlaywrightSeeder`, `FantreffenPlaywrightSeeder` (nur Test-Env)

## Code Reviews

- ⚠️ **Punkte aus Code Reviews erst validieren** – Prüfe ob der Vorschlag im Projektkontext sinnvoll ist, bevor du ihn umsetzt
- Nicht jede Review-Empfehlung passt zu diesem Projekt (z.B. deutsche Domain-Sprache, SQLite-Kompatibilität)

## Nicht tun

- ❌ Keine `.env`-Werte in Commits
- ❌ Keine MySQL-Only-Syntax (CI nutzt SQLite)
- ❌ Kein Ändern von `tests/TestCase.php` HTTP-Fakes ohne Grund
- ❌ **Keine automatischen Git-Commits** – der Entwickler committet selbst
- ❌ Keine `{!! !!}` ohne XSS-Prüfung – verwende `{{ }}`
- ❌ Code Coverage nicht unter 75% fallen lassen

## PR-Checkliste

```bash
php artisan test              # ✓ PHPUnit
npm run test:vitest           # ✓ JS (falls Frontend)
npm run build                 # ✓ Assets (falls geändert)
./vendor/bin/pint --test      # ✓ Code-Style
```

**Coverage-Ziel:** PHP und JS Coverage bei **≥75%** halten oder steigern. Neue Features mit Tests abdecken.

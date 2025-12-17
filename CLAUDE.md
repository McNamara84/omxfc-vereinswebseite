# CLAUDE.md - AI Assistant Guide for OMXFC Vereinswebseite

This document provides comprehensive guidance for AI assistants working with the OMXFC (Offizieller MADDRAX Fanclub) Vereinswebseite codebase.

## Table of Contents

1. [Project Overview](#project-overview)
2. [Tech Stack](#tech-stack)
3. [Codebase Structure](#codebase-structure)
4. [Architectural Patterns](#architectural-patterns)
5. [Development Workflows](#development-workflows)
6. [Testing Conventions](#testing-conventions)
7. [Database Patterns](#database-patterns)
8. [Frontend Architecture](#frontend-architecture)
9. [Common Development Tasks](#common-development-tasks)
10. [Coding Conventions](#coding-conventions)
11. [File Locations Reference](#file-locations-reference)
12. [Tips for AI Assistants](#tips-for-ai-assistants)

---

## Project Overview

**Project Name:** OMXFC Vereinswebseite
**Description:** Official website for the Offizieller MADDRAX Fanclub (OMXFC), a German science fiction fan club
**License:** GPL-3.0-or-later
**Repository:** https://github.com/McNamara84/omxfc-vereinswebseite
**Language:** German (UI, routes, documentation, and domain terminology)

### Key Features

- **Public Pages:** Club history (Chronik), events (Termine), constitution (Satzung), honorary members
- **Member Management:** Online applications, member dashboard, working groups (Arbeitsgruppen)
- **Event System:** Maddrax-Fantreffen 2026 registration with PayPal integration, T-shirt orders
- **Community Features:** Book exchange (Romantauschbörse), reviews (Rezensionen), audiobooks (Hörbücher)
- **Task Management:** Todo system with team assignments and point rewards
- **Content Management:** Mini-games (Maddraxiversum), meeting protocols, treasurer module (Kassenbuch)
- **Interactive Member Map:** Leaflet-based map with MarkerCluster showing member locations

---

## Tech Stack

### Backend
- **Framework:** Laravel 12 (latest version)
- **PHP:** 8.2+ (tested up to PHP 8.5)
- **Key Packages:**
  - Laravel Jetstream 5.3 (team management, authentication)
  - Laravel Sanctum 4.0 (API authentication)
  - Livewire 3.6 (reactive components)
  - Laravel Scout 10.14 with TNTSearch (full-text search)
  - Spatie Laravel PDF (PDF generation)
  - Spatie Laravel Sitemap 7.3 (sitemap generation)

### Frontend
- **CSS Framework:** Tailwind CSS 3.4 with plugins (@tailwindcss/forms, @tailwindcss/typography)
- **Build Tool:** Vite 6.0
- **JavaScript:**
  - Alpine.js (via Jetstream)
  - Alpine.js Focus plugin 3.14
  - Chart.js 4.4 (data visualization)
  - Leaflet 1.9 (maps)
  - Leaflet MarkerCluster 1.5 (map clustering)
  - Simple Datatables 9.2 (table enhancement)

### Testing & Quality
- **PHP Testing:** PHPUnit 11.5
- **JavaScript Testing:**
  - Jest 30.0 (legacy tests)
  - Vitest 3.2 (modern tests)
  - Playwright 1.47 (E2E tests)
  - @axe-core/playwright 4.10 (accessibility testing)
- **Code Style:** Laravel Pint 1.13

### Development Tools
- **Package Manager:** Composer 2.6+, npm 10
- **Node.js:** v20 LTS
- **Database:** MariaDB/MySQL (production), SQLite (testing)
- **Queue:** Database driver (can be configured for Redis)
- **Cache:** Database driver (can be configured for Redis)

---

## Codebase Structure

### Directory Layout

```
omxfc-vereinswebseite/
├── app/
│   ├── Actions/              # Jetstream actions (Fortify, Jetstream)
│   ├── Console/Commands/     # Artisan commands (crawlers, utilities)
│   ├── Enums/                # PHP 8.1+ backed enums
│   ├── Http/
│   │   ├── Controllers/      # HTTP controllers (28+ controllers)
│   │   │   ├── Api/          # API controllers
│   │   │   └── Auth/         # Authentication controllers
│   │   ├── Middleware/       # Custom middleware (10+ middleware)
│   │   └── Requests/         # Form request validation classes
│   ├── Jobs/                 # Queue jobs (GeocodeUser)
│   ├── Livewire/             # Livewire components
│   │   └── Profile/          # Profile-related Livewire components
│   ├── Mail/                 # Mailable classes (10+ email types)
│   ├── Models/               # Eloquent models (24+ models)
│   ├── Policies/             # Authorization policies (6 policies)
│   ├── Rules/                # Custom validation rules
│   ├── Services/             # Business logic services (8 services)
│   ├── Support/              # Helper classes
│   └── View/Components/      # Blade components
├── bootstrap/
├── config/                   # Laravel configuration files
├── database/
│   ├── factories/            # Model factories for testing
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── deploy/                   # Deployment configurations
│   ├── nginx/                # Nginx configs
│   └── php/                  # PHP configs
├── lang/                     # Localization files
├── public/                   # Public assets
│   ├── favicon/              # Favicon assets
│   └── images/               # Static images
├── resources/
│   ├── css/                  # CSS files (Tailwind)
│   │   └── app.css           # Main stylesheet
│   ├── js/                   # JavaScript modules
│   │   ├── utils/            # Utility modules
│   │   ├── mitglieder/       # Member-specific JS
│   │   ├── kassenbuch/       # Financial module JS
│   │   ├── dashboard/        # Dashboard JS
│   │   └── protokolle/       # Protocol JS
│   └── views/                # Blade templates
│       ├── components/       # Reusable Blade components
│       ├── emails/           # Email templates
│       ├── fantreffen/       # Event registration views
│       ├── hoerbuecher/      # Audiobook views
│       ├── kassenbuch/       # Treasurer views
│       ├── livewire/         # Livewire component views
│       ├── mitglieder/       # Member management views
│       ├── pages/            # Static pages
│       ├── reviews/          # Review system views
│       └── romantausch/      # Book exchange views
├── routes/
│   ├── api.php               # API routes (minimal)
│   ├── console.php           # Console routes
│   └── web.php               # Web routes (main routing file)
├── storage/
├── tests/
│   ├── Concerns/             # Reusable test traits
│   ├── e2e/                  # Playwright E2E tests
│   ├── Feature/              # Feature tests (20+)
│   ├── Jest/                 # Jest tests
│   ├── Unit/                 # Unit tests (14+)
│   └── Vitest/               # Vitest tests
├── .github/
│   ├── actions/              # Reusable GitHub Actions
│   └── workflows/            # CI/CD workflows
├── Dockerfile                # Multi-stage Docker build
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
├── phpunit.xml               # PHPUnit configuration
├── playwright.config.js      # Playwright configuration
├── tailwind.config.js        # Tailwind CSS configuration
├── vite.config.js            # Vite build configuration
└── jest.config.js            # Jest configuration
```

### Key Models

- **User** (`app/Models/User.php`) - Central authentication model with team relationships, fan preferences, geocoding
- **Team** (`app/Models/Team.php`) - Working groups (Arbeitsgruppen) with members and todos
- **Review** (`app/Models/Review.php`) - Book reviews with formatted content, comments, soft deletes
- **FantreffenAnmeldung** (`app/Models/FantreffenAnmeldung.php`) - Event registrations with payment tracking
- **Todo** (`app/Models/Todo.php`) - Task management with team assignments, status tracking
- **Book** (`app/Models/Book.php`) - Book catalog for the Maddrax series
- **BookOffer/BookRequest/BookSwap** - Book exchange system (Romantauschbörse)
- **AudiobookEpisode/AudiobookRole** - Audiobook production management
- **KassenbuchEntry** (`app/Models/KassenbuchEntry.php`) - Financial transactions
- **Mission** (`app/Models/Mission.php`) - Mission Mars series tracking
- **RomanExcerpt** (`app/Models/RomanExcerpt.php`) - Book excerpts

---

## Architectural Patterns

### Organization

This codebase follows a **hybrid architecture**:
- **Traditional Laravel MVC** structure (organized by type, not by feature)
- **Service Layer Pattern** for complex business logic
- **Livewire Components** for reactive forms and dashboards
- **Policy-Based Authorization** for access control
- **Event-Driven** patterns where appropriate

### Service Layer Pattern

Business logic is extracted to dedicated service classes when complexity warrants it:

**Example Services:**
- `TeamPointService` - Point calculations, leaderboards, team metrics
- `BrowserStatsService` - Browser statistics aggregation
- `MaddraxDataService` - Maddrax series data aggregation
- `FantreffenDeadlineService` - Event deadline logic
- `RomantauschInfoProvider` - Book exchange information

**Usage Pattern:**
```php
// Constructor injection with readonly properties (PHP 8.1+)
public function __construct(
    private readonly TeamPointService $teamPointService
) {}

// Use in controller methods
public function index()
{
    $leaderboard = $this->teamPointService->getTeamLeaderboard();
    return view('teams.index', compact('leaderboard'));
}
```

### Livewire Component Pattern

Livewire is used for interactive forms and real-time dashboards:

**Key Components:**
- `FantreffenAnmeldung` - Event registration form with real-time validation
- `FantreffenAdminDashboard` - Admin dashboard with toggles and filtering
- `FantreffenZahlungsbestaetigung` - Payment confirmation handling

**Pattern:**
```php
class FantreffenAnmeldung extends Component
{
    // Public properties are automatically bound to the view
    public $name = '';
    public $email = '';

    // Real-time validation on property update
    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    // Form submission
    public function submit()
    {
        $validated = $this->validate();
        // Process...
    }

    // Render with layout
    public function render()
    {
        return view('livewire.fantreffen-anmeldung')
            ->layout('layouts.app', ['title' => 'Anmeldung']);
    }
}
```

### Authorization Pattern

**Policy-Based Authorization:**
```php
// In controller
$this->authorize('update', $bookOffer);
$this->authorize('delete', $todo);
```

**Role-Based Middleware:**
```php
// Route protection
Route::middleware(['admin'])->group(function () {
    // Admin-only routes
});

Route::middleware(['vorstand-or-kassenwart'])->group(function () {
    // Board or treasurer routes
});
```

**Custom Role Helpers on User Model:**
```php
// app/Models/User.php
public function hasRole(Role $role): bool
public function hasAnyRole(Role ...$roles): bool
public function hasVorstandRole(): bool
public function isAdmin(): bool
```

### Enum Pattern (PHP 8.1+)

```php
// app/Enums/Role.php
enum Role: string
{
    case Admin = 'Admin';
    case Vorstand = 'Vorstand';
    case Kassenwart = 'Kassenwart';
    // ...
}

// app/Enums/TodoStatus.php
enum TodoStatus: string
{
    case Offen = 'Offen';
    case InBearbeitung = 'In Bearbeitung';
    case Abgeschlossen = 'Abgeschlossen';
}
```

### Job Pattern

Jobs are used sparingly, dispatched from model events:

```php
// app/Jobs/GeocodeUser.php
// Dispatched when user address changes
// Handles async geocoding for map display
```

### Model Patterns

**Heavy use of relationships:**
```php
// app/Models/User.php
public function teams(): BelongsToMany
public function createdTodos(): HasMany
public function assignedTodos(): HasMany
public function points(): HasMany
```

**Accessors/Mutators:**
```php
// app/Models/Review.php
public function getFormattedContentAttribute(): string
{
    return Cache::remember(
        "review_{$this->id}_formatted_content",
        now()->addDay(),
        fn () => $this->formatContent()
    );
}
```

**Model Events:**
```php
protected static function booted()
{
    static::saved(function ($user) {
        if ($user->addressChanged()) {
            GeocodeUser::dispatch($user);
        }
    });
}
```

---

## Development Workflows

### Local Development Setup

```bash
# 1. Clone and install dependencies
git clone https://github.com/McNamara84/omxfc-vereinswebseite.git
cd omxfc-vereinswebseite
composer install
npm install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Database setup (configure DB_* in .env first)
php artisan migrate
php artisan db:seed --class=DefaultAdminAndTeamSeeder
php artisan db:seed --class=TodoCategorySeeder

# 4. Build assets
npm run build  # Production
# OR
npm run dev    # Development with hot reload
```

### Running Development Server

**Option 1: Separate processes**
```bash
php artisan serve          # Terminal 1
npm run dev                # Terminal 2
php artisan queue:work     # Terminal 3 (if using queues)
```

**Option 2: Combined (recommended)**
```bash
composer run dev
# Runs server, queue worker, and Vite in parallel with color-coded output
```

### Available Artisan Commands

**Development:**
```bash
php artisan serve              # Start development server
php artisan tinker             # REPL
php artisan pail               # Log viewer
```

**Database:**
```bash
php artisan migrate            # Run migrations
php artisan migrate:fresh      # Drop all tables and re-migrate
php artisan migrate:rollback   # Rollback last migration
php artisan db:seed            # Run seeders
```

**Cache Management:**
```bash
php artisan cache:clear        # Clear application cache
php artisan config:cache       # Cache config (production)
php artisan config:clear       # Clear config cache
php artisan route:cache        # Cache routes (production)
php artisan route:clear        # Clear route cache
php artisan view:cache         # Cache views (production)
php artisan view:clear         # Clear view cache
```

**Content Management:**
```bash
php artisan romane:index              # Index novels for search
php artisan romane:index --fresh      # Re-index from scratch
php artisan books:import              # Import book data
php artisan reviews:import-old --fresh # Import old reviews
```

**Web Crawlers:**
```bash
php artisan crawlnovels        # Crawl Maddrax main series
php artisan crawlhardcovers    # Crawl Maddrax hardcovers
php artisan crawlmissionmars   # Crawl Mission Mars series
php artisan crawl2012          # Crawl 2012 mini-series
php artisan crawlvolkdertiefe  # Crawl Das Volk der Tiefe series
```

**Utilities:**
```bash
php artisan sitemap:generate      # Generate sitemap.xml
php artisan member-map:refresh    # Update member map cache
php artisan schedule:run          # Run scheduled tasks (cron)
```

### Testing Commands

```bash
# PHP Tests
php artisan test                    # Run all PHPUnit tests
php artisan test --filter=TodoTest  # Run specific test
php artisan test --coverage         # With coverage

# JavaScript Tests
npm test                 # Run Jest tests
npm run test:vitest      # Run Vitest tests
npm run test:e2e         # Run Playwright E2E tests

# Code Style
./vendor/bin/pint        # Auto-fix PHP code style
./vendor/bin/pint --test # Check without fixing
```

### Git Workflow

**Branch Strategy:**
- `main` - Production branch
- Feature branches: `feature/description` or `claude/session-id-xyz`

**Commit Guidelines:**
- Write clear, descriptive commit messages
- Focus on "why" rather than "what"
- Use imperative mood: "Add feature" not "Added feature"
- Keep commits atomic and focused

**Example Workflow:**
```bash
git checkout -b feature/add-book-rating-system
# Make changes...
git add .
git commit -m "Add star rating system for book reviews"
git push -u origin feature/add-book-rating-system
# Create PR on GitHub
```

---

## Testing Conventions

### Test Organization

```
tests/
├── Concerns/          # Reusable test traits
├── Feature/           # Feature tests (HTTP, integration)
├── Unit/              # Unit tests (isolated logic)
├── Jest/              # Legacy JavaScript tests
├── Vitest/            # Modern JavaScript tests
└── e2e/               # Playwright end-to-end tests
```

### Common Testing Patterns

**RefreshDatabase Trait:**
```php
use RefreshDatabase;

// Automatically migrates and rolls back database for each test
```

**Test Concerns/Traits:**
```php
// tests/Concerns/CreatesMemberClientSnapshot.php
trait CreatesMemberClientSnapshot
{
    protected function createSnapshot(array $data = []): MemberClientSnapshot
    {
        // Reusable snapshot creation logic
    }
}
```

**Factory Pattern:**
```php
$user = User::factory()->create([
    'email' => 'test@example.com',
    'role' => Role::Admin,
]);

$team = Team::factory()->create();
```

**Helper Methods:**
```php
private function actingMemberWithPoints(int $points): User
{
    $user = User::factory()->create(['role' => Role::Mitglied]);
    $user->points()->create(['punkte' => $points]);
    return $this->actingAs($user);
}
```

**Time Manipulation:**
```php
use Carbon\Carbon;

Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
// Test time-sensitive features
Carbon::setTestNow(); // Reset
```

**Authorization Testing:**
```php
$this->actingAs($admin)
    ->get('/admin/dashboard')
    ->assertOk();

$this->actingAs($regularUser)
    ->get('/admin/dashboard')
    ->assertForbidden();
```

### Test Coverage

The project maintains test coverage badges for both PHP and JavaScript:
- PHP Coverage: Feature tests with PHPUnit
- JS Coverage: Unit tests with Jest
- E2E Coverage: Playwright tests across Chromium, Firefox, WebKit
- Accessibility: Axe-core checks in E2E tests

---

## Database Patterns

### Migration Conventions

**Anonymous Class Pattern (Modern Laravel):**
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();
        });
    }
};
```

**Key Patterns:**
- Foreign keys with cascade deletes: `->constrained()->cascadeOnDelete()`
- Nullable foreign keys for optional relationships: `->nullable()`
- Enum columns for status fields
- Soft deletes where appropriate: `$table->softDeletes()`
- Timestamps on most tables: `$table->timestamps()`

### Model Relationships

**Common Relationship Types:**
```php
// One-to-Many
public function todos(): HasMany
{
    return $this->hasMany(Todo::class, 'team_id');
}

// Many-to-One
public function team(): BelongsTo
{
    return $this->belongsTo(Team::class);
}

// Many-to-Many
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, Membership::class)
        ->withTimestamps();
}

// Has Many Through (example)
public function teamTodos(): HasManyThrough
{
    return $this->hasManyThrough(Todo::class, Team::class);
}
```

### Database Seeding

**Available Seeders:**
- `DatabaseSeeder` - Main seeder, calls other seeders
- `DefaultAdminAndTeamSeeder` - Creates admin user and members team
- `TodoCategorySeeder` - Populates todo categories
- `DashboardSampleSeeder` - Sample data for dashboard
- `TodoPlaywrightSeeder` - Test data for E2E tests (test env only)
- `UpdateAudiobookEpisodeStatusSeeder` - Data migration seeder

**Usage:**
```bash
php artisan db:seed                              # Run DatabaseSeeder
php artisan db:seed --class=DefaultAdminAndTeamSeeder
php artisan migrate:fresh --seed                 # Fresh migration + seed
```

---

## Frontend Architecture

### Blade Component Structure

**Component Organization:**
```
resources/views/components/
├── forms/              # Form-specific components
│   ├── select-field.blade.php
│   ├── text-field.blade.php
│   └── range-field.blade.php
├── modal.blade.php     # Reusable modal
├── button.blade.php    # Button component
├── input.blade.php     # Input component
└── ...
```

**Component Usage:**
```blade
{{-- Using a form component --}}
<x-forms.text-field
    name="email"
    label="E-Mail-Adresse"
    :value="old('email')"
    required
/>

{{-- Using slots --}}
<x-modal>
    <x-slot name="title">Confirmation</x-slot>
    <x-slot name="content">Are you sure?</x-slot>
    <x-slot name="actions">
        <x-button>Confirm</x-button>
    </x-slot>
</x-modal>
```

**Component Props:**
```blade
{{-- Define props with defaults --}}
@props(['title', 'href' => null, 'active' => false])

{{-- Use in component --}}
<a href="{{ $href }}" @class(['active' => $active])>
    {{ $title }}
</a>
```

### JavaScript Module Organization

**Vite Entry Points:**
```javascript
// vite.config.js
input: [
    'resources/css/app.css',
    'resources/js/app.js',           // Main app JS
    'resources/js/maddraxiversum.js', // Mini-games
    'resources/js/statistik.js',      // Statistics
    'resources/js/hoerbuecher.js',    // Audiobooks
    'resources/js/fantreffen.js',     // Event system
    // ... more feature-specific files
]
```

**Module Pattern:**
```javascript
// resources/js/utils/dashboard.js
export function initializeDashboard() {
    // Dashboard initialization logic
}

// resources/js/app.js
import { initializeDashboard } from './utils/dashboard';

document.addEventListener('DOMContentLoaded', () => {
    initializeDashboard();
});
```

**Import in Blade:**
```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Feature-specific JS --}}
@vite('resources/js/maddraxiversum.js')
```

### Tailwind CSS Patterns

**Configuration:**
- Dark mode: Class-based (`class="dark"`)
- Custom font: Figtree
- Plugins: forms, typography

**Common Utilities:**
```html
<!-- Dark mode support -->
<div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">

<!-- Responsive design -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

<!-- Accessibility -->
<span class="sr-only">Screen reader only text</span>
```

**Brand Colors:**
- Primary Red: `#8B0116`
- Dark Mode Red: `#FCA5A5`

### Dark Mode Implementation

**Theme System:**
```javascript
// resources/js/app.js
function __omxfcApplySystemTheme() {
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

function __omxfcApplyStoredTheme() {
    const theme = localStorage.getItem('theme');
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (theme === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        __omxfcApplySystemTheme();
    }
}
```

---

## Common Development Tasks

### Adding a New Feature

1. **Create Route** (`routes/web.php`):
```php
Route::prefix('feature')->name('feature.')->group(function () {
    Route::get('/', [FeatureController::class, 'index'])->name('index');
    Route::get('/create', [FeatureController::class, 'create'])->name('create');
    Route::post('/', [FeatureController::class, 'store'])->name('store');
});
```

2. **Create Migration**:
```bash
php artisan make:migration create_feature_table
# Edit migration file, then:
php artisan migrate
```

3. **Create Model**:
```bash
php artisan make:model Feature
# Add relationships, accessors, etc.
```

4. **Create Controller**:
```bash
php artisan make:controller FeatureController
```

5. **Create Views**:
```
resources/views/feature/
├── index.blade.php
├── create.blade.php
└── show.blade.php
```

6. **Add Tests**:
```bash
php artisan make:test FeatureTest
```

### Adding a New Livewire Component

```bash
php artisan make:livewire FeatureName

# Creates:
# - app/Livewire/FeatureName.php
# - resources/views/livewire/feature-name.blade.php
```

### Adding Custom Middleware

```bash
php artisan make:middleware EnsureCustomRole

# Register in bootstrap/app.php or directly in routes
Route::middleware(['custom-role'])->group(function () {
    // Protected routes
});
```

### Adding a New Service

```php
// app/Services/FeatureService.php
namespace App\Services;

class FeatureService
{
    public function processFeature(array $data): void
    {
        // Business logic
    }
}

// Use in controller
public function __construct(
    private readonly FeatureService $featureService
) {}
```

### Updating Scout Index

```bash
# Index all models
php artisan scout:import "App\Models\Book"

# Flush and re-import
php artisan scout:flush "App\Models\Book"
php artisan scout:import "App\Models\Book"
```

### Adding Email Notifications

```bash
php artisan make:mail FeatureNotification

# Use in controller/job
Mail::to($user)->send(new FeatureNotification($data));
```

---

## Coding Conventions

### Naming Conventions

**German Terminology:**
- Use German for domain-specific terms: `Mitglied`, `Aufgabe`, `Rezension`
- Routes use German: `/mitglieder`, `/aufgaben`, `/rezensionen`
- Database tables use German: `fantreffen_anmeldungen`, `kassenbuch_entries`
- Model names use German: `FantreffenAnmeldung`, `KassenbuchEntry`

**Route Naming:**
```php
// Pattern: {prefix}.{action}
Route::name('mitglieder.')->group(function () {
    Route::get('/', ...)->name('index');      // mitglieder.index
    Route::get('/create', ...)->name('create'); // mitglieder.create
});
```

**Class Naming:**
- Controllers: `{Feature}Controller` (e.g., `RomantauschController`)
- Services: `{Feature}Service` (e.g., `TeamPointService`)
- Policies: `{Model}Policy` (e.g., `BookOfferPolicy`)
- Livewire: `{Feature}{Purpose}` (e.g., `FantreffenAnmeldung`)

### PHP Code Style

**Modern PHP Features (8.2+):**
```php
// Readonly properties
public function __construct(
    private readonly TeamPointService $service
) {}

// Backed enums
enum Role: string
{
    case Admin = 'Admin';
    case Vorstand = 'Vorstand';
}

// Type declarations
public function getPoints(): int
public function getUsers(): Collection
```

**Laravel Pint:**
- Follow Laravel's code style
- Run `./vendor/bin/pint` before committing
- Configuration follows Laravel defaults

### Validation Patterns

**Form Requests:**
```php
// app/Http/Requests/FeatureRequest.php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
    ];
}

public function messages(): array
{
    return [
        'name.required' => 'Der Name ist erforderlich.',
    ];
}
```

**Inline Validation:**
```php
$validated = $request->validate([
    'title' => 'required|string|max:255',
    'content' => 'required|string',
]);
```

**Livewire Validation:**
```php
protected $rules = [
    'email' => 'required|email',
    'name' => 'required|min:3',
];

public function updated($propertyName)
{
    $this->validateOnly($propertyName);
}
```

### Authorization Best Practices

**Use Policies:**
```php
// Check in controller
$this->authorize('update', $model);

// Check in Blade
@can('update', $model)
    <a href="...">Edit</a>
@endcan

// Check in code
if ($user->can('update', $model)) {
    // ...
}
```

**Use Middleware for Routes:**
```php
Route::middleware(['admin'])->group(function () {
    // Admin only
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Authenticated and verified users
});
```

### Database Query Best Practices

**Eager Loading (Avoid N+1):**
```php
// Good
$reviews = Review::with(['user', 'book'])->get();

// Bad
$reviews = Review::all();
foreach ($reviews as $review) {
    echo $review->user->name; // N+1 query problem
}
```

**Query Scopes:**
```php
// In model
public function scopePublished($query)
{
    return $query->whereNotNull('published_at');
}

// Usage
$reviews = Review::published()->get();
```

**Use Chunking for Large Datasets:**
```php
Book::chunk(100, function ($books) {
    foreach ($books as $book) {
        // Process book
    }
});
```

---

## File Locations Reference

### Key Configuration Files

| File | Purpose |
|------|---------|
| `config/app.php` | App-wide configuration |
| `config/database.php` | Database connections |
| `config/mail.php` | Mail configuration |
| `config/scout.php` | Search configuration |
| `.env` | Environment variables |
| `.env.example` | Environment template |

### Routes

| File | Purpose |
|------|---------|
| `routes/web.php` | Web routes (main routing) |
| `routes/api.php` | API routes (minimal) |
| `routes/console.php` | Console routes |

### Views by Feature

| Feature | Location |
|---------|----------|
| Public pages | `resources/views/pages/` |
| Reviews | `resources/views/reviews/` |
| Event system | `resources/views/fantreffen/` |
| Member area | `resources/views/mitglieder/` |
| Book exchange | `resources/views/romantausch/` |
| Audiobooks | `resources/views/hoerbuecher/` |
| Treasurer | `resources/views/kassenbuch/` |
| Todo system | `resources/views/todos/` |
| Email templates | `resources/views/emails/` |

### JavaScript by Feature

| Feature | Location |
|---------|----------|
| Main app | `resources/js/app.js` |
| Dashboard | `resources/js/dashboard/` |
| Member map | `resources/js/mitglieder/` |
| Statistics | `resources/js/statistik.js` |
| Mini-games | `resources/js/maddraxiversum.js` |
| Audiobooks | `resources/js/hoerbuecher.js` |
| Event system | `resources/js/fantreffen.js` |

### Tests by Type

| Type | Location |
|------|----------|
| Feature tests | `tests/Feature/` |
| Unit tests | `tests/Unit/` |
| E2E tests | `tests/e2e/` |
| Jest tests | `tests/Jest/` |
| Vitest tests | `tests/Vitest/` |

---

## Tips for AI Assistants

### Before Making Changes

1. **Read existing code first** - Always use the Read tool to understand current implementation
2. **Check related tests** - Look for existing tests to understand expected behavior
3. **Review similar features** - Find analogous implementations in the codebase
4. **Understand German terminology** - Routes, models, and domain concepts use German

### When Adding Features

1. **Follow existing patterns** - Match the architectural style of similar features
2. **Add tests** - Create Feature and/or Unit tests for new functionality
3. **Update documentation** - Keep README.md and this file updated
4. **Consider accessibility** - Use semantic HTML, ARIA labels, keyboard navigation
5. **Support dark mode** - Add dark mode variants to all new UI components

### Common Pitfalls to Avoid

1. **Don't mix languages** - Keep German for domain terms, English for code
2. **Don't skip authorization** - Always add policy/middleware for protected features
3. **Don't ignore validation** - Validate all user input (frontend + backend)
4. **Don't create N+1 queries** - Use eager loading with `with()`
5. **Don't forget migrations** - Database changes need migrations
6. **Don't skip dark mode** - All UI should support dark mode
7. **Don't ignore mobile** - Use responsive Tailwind classes

### Testing Checklist

When adding features, ensure:
- [ ] Feature test for HTTP requests
- [ ] Unit test for business logic
- [ ] Policy test for authorization
- [ ] E2E test for critical user flows (if applicable)
- [ ] Accessibility check with axe-core (for UI changes)
- [ ] Tests pass: `php artisan test && npm test`
- [ ] Code style passes: `./vendor/bin/pint --test`

### Performance Considerations

1. **Use caching** - Cache expensive queries (see `Review::getFormattedContentAttribute()`)
2. **Queue heavy operations** - Use jobs for long-running tasks
3. **Optimize queries** - Use select(), limit(), eager loading
4. **Index database columns** - Add indexes to frequently queried columns
5. **Lazy load assets** - Use Vite code splitting for feature-specific JS

### Security Best Practices

1. **Validate all input** - Never trust user input
2. **Use policies** - Don't rely on middleware alone for authorization
3. **Sanitize output** - Escape data in Blade templates (use `{{ }}` not `{!! !!}`)
4. **Use CSRF protection** - All forms should include `@csrf`
5. **Protect routes** - Apply middleware to sensitive routes
6. **Hash passwords** - Use bcrypt (Laravel default)
7. **Validate file uploads** - Check MIME types, sizes, extensions

### Accessibility Guidelines

1. **Semantic HTML** - Use appropriate tags (`<nav>`, `<main>`, `<article>`)
2. **ARIA labels** - Add `aria-label` for icon buttons, complex widgets
3. **Keyboard navigation** - Ensure all interactive elements are keyboard accessible
4. **Screen reader text** - Use `sr-only` class for context
5. **Color contrast** - Ensure sufficient contrast in light and dark modes
6. **Focus indicators** - Maintain visible focus states
7. **Alt text** - Always add descriptive alt text to images

### Working with German Terminology

**Common German Terms in Codebase:**
- **Mitglied/Mitglieder** - Member/Members
- **Aufgaben** - Tasks/Todos
- **Rezension/Rezensionen** - Review/Reviews
- **Romantausch** - Book exchange
- **Fantreffen** - Fan meeting/convention
- **Kassenbuch** - Cash book/ledger
- **Hörbücher** - Audiobooks
- **Vorstand** - Board (of directors)
- **Kassenwart** - Treasurer
- **Anwärter** - Applicant
- **Satzung** - Constitution/bylaws
- **Chronik** - Chronicle/history

### Environment Variables to Note

**Essential:**
- `APP_URL` - Must be set correctly for links, emails, sitemap
- `MAIL_*` - Configure for email sending
- `DB_*` - Database connection
- `QUEUE_CONNECTION` - Set to `database` or `redis`

**Feature-Specific:**
- `PAYPAL_ME_USERNAME` - PayPal.me username for Fantreffen
- `PAYPAL_FANTREFFEN_EMAIL` - Contact email for PayPal issues
- `FANTREFFEN_TSHIRT_DEADLINE` - T-shirt order deadline

### Useful Laravel Helpers

```php
// URL generation
route('mitglieder.index')
url('/path')
asset('images/logo.png')

// Translation
__('messages.welcome')
trans('messages.welcome')

// Session
session('key')
session()->flash('status', 'Success!')

// Auth
auth()->user()
auth()->check()
auth()->id()

// Redirects
redirect()->route('home')
redirect()->back()
redirect()->back()->with('error', 'Message')

// Responses
response()->json($data)
response()->download($path)
response()->view('view', $data)
```

---

## CI/CD Workflows

### GitHub Actions

**Workflows:**
- `phpunit.yml` - PHP tests on PHP 8.2, 8.3, 8.4, 8.5 with coverage
- `playwright.yml` - E2E tests on Chromium, Firefox, WebKit
- `vitest.yml` - Vitest JavaScript tests
- `summary.yml` - Workflow summary generation
- `deploy.yml` - Deployment workflow
- `performance-benchmark.yml` - Performance testing
- `php-8-5-compatibility.yml` - Future PHP compatibility

**Coverage Badges:**
- Generated on `main` branch pushes
- Stored in `image-data` branch
- Displayed in README.md

### Deployment

**Docker Build:**
```bash
docker build -t omxfc-vereinswebseite .
```

**Production Checklist:**
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan migrate --force
```

**Scheduler:**
```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

---

## Additional Resources

- **Laravel Documentation:** https://laravel.com/docs/12.x
- **Livewire Documentation:** https://livewire.laravel.com/docs
- **Tailwind CSS Documentation:** https://tailwindcss.com/docs
- **Repository:** https://github.com/McNamara84/omxfc-vereinswebseite
- **Issues:** https://github.com/McNamara84/omxfc-vereinswebseite/issues

---

**Last Updated:** 2025-12-17
**Laravel Version:** 12.0
**PHP Version:** 8.2+
**Node Version:** 20 LTS

<?php

namespace App\Providers;

use App\Enums\PollVisibility;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureMaddraxikonAdmin;
use App\Livewire\Profile\LogoutOtherBrowserSessionsForm;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Livewire\Teams\TeamMemberManager;
use App\Livewire\Teams\UpdateTeamNameForm;
use App\Services\Polls\ActivePollResolver;
use App\Services\TourAssignmentService;
use App\Support\Navigation\NavigationBuilder;
use App\Support\TestingBladeComponentRegistry;
use App\View\Components\Alert;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! Route::hasMacro('livewire')) {
            Route::macro('livewire', function (string $uri, string $component) {
                return Route::get($uri, $component);
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production – URL::forceScheme() erzwingt https:// bei der URL-Generierung.
        // Zusätzlich müssen X-Forwarded-Proto und X-Forwarded-Port gesetzt werden, damit
        // request()->isSecure() und request()->getPort() die korrekten Werte liefern.
        // Ohne diese Header schlägt die Signaturprüfung bei Livewire File-Uploads fehl (401),
        // weil die signierte URL https:// nutzt, aber request()->url() http://:80 zurückgibt.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            request()->headers->set('X-Forwarded-Proto', 'https');
            request()->headers->set('X-Forwarded-Port', '443');
        }

        if ($this->app->environment('testing')) {
            // Tests should use built assets instead of inheriting a local dev hot file.
            Vite::useHotFile(public_path('testing.hot'));
        }

        if (env('PLAYWRIGHT_USE_DOCKER') === '1') {
            Vite::useHotFile(public_path('playwright.hot'));
        }

        // Rate Limiter für Fantreffen-Anmeldung (deaktivierbar via Config für Tests)
        RateLimiter::for('fantreffen-registration', function ($request) {
            if (config('services.fantreffen.disable_rate_limit')) {
                return Limit::none();
            }

            return Limit::perHour(15)->by($request->ip());
        });

        RateLimiter::for('arbeitsgruppen-kontakt', function ($request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('database-dump', function ($request) {
            return Limit::perMinute(3)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('database-restore', function ($request) {
            return Limit::perHour(3)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('maddraxikon-oauth-start', function ($request) {
            return Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('maddraxikon-oauth-callback', function ($request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('maddraxikon-oauth-disconnect', function ($request) {
            return Limit::perMinute(3)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        $version = Config::get('app.version');

        if ($version === null || $version === '0.0.0') {
            $version = '0.0.0';

            try {
                $commit = '';
                $revList = Process::run(['git', 'rev-list', '--tags', '--max-count=1']);

                if ($revList->successful()) {
                    $commit = trim($revList->output());
                }

                if ($commit !== '') {
                    $describe = Process::run(['git', 'describe', '--tags', $commit]);

                    if ($describe->successful()) {
                        $version = trim($describe->output());
                    }
                }
            } catch (\Throwable $e) {
                // use default version
            }
        }

        View::share('appVersion', $version);

        View::composer(['layouts.app', 'layouts.guest'], function ($view) {
            try {
                $defaultImagePath = Vite::asset('resources/images/omxfc-logo.png');
            } catch (\Throwable $e) {
                $defaultImagePath = 'resources/images/omxfc-logo.png';
            }

            if ($defaultImagePath === '') {
                $defaultImagePath = 'resources/images/omxfc-logo.png';
            }

            $data = $view->getData();
            $socialImagePath = $data['image'] ?? $defaultImagePath;
            $socialImage = filter_var($socialImagePath, FILTER_VALIDATE_URL)
                ? $socialImagePath
                : asset($socialImagePath);

            $view->with('socialImage', $socialImage);
        });

        View::composer('navigation-menu', function ($view) {
            $navigationContext = [
                'activePollMenuLabel' => null,
                'showActivePollForAuth' => false,
                'showActivePollForGuest' => false,
            ];

            if (Schema::hasTable('polls')) {
                $cacheKey = 'polls.active_for_menu.v2';
                $navigationContext = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                    $poll = app(ActivePollResolver::class)->current();
                    $isWithinWindow = $poll ? $poll->isWithinVotingWindow() : false;

                    return [
                        'activePollMenuLabel' => $poll?->menu_label,
                        'showActivePollForAuth' => (bool) ($poll && $isWithinWindow),
                        'showActivePollForGuest' => (bool) ($poll && $isWithinWindow && $poll->visibility === PollVisibility::Public),
                    ];
                });
            }

            $view->with([
                'activePollForMenu' => null,
                'activePollMenuLabel' => $navigationContext['activePollMenuLabel'],
                'showActivePollForAuth' => $navigationContext['showActivePollForAuth'],
                'showActivePollForGuest' => $navigationContext['showActivePollForGuest'],
                'navigation' => app(NavigationBuilder::class)->build(Auth::user(), $navigationContext),
            ]);
        });

        View::composer('profile.show', function ($view) {
            $tourOverview = collect();

            if (Auth::check() && Schema::hasTable('tour_assignments')) {
                $tourOverview = app(TourAssignmentService::class)
                    ->selfServiceOverviewForUser(Auth::user());
            }

            $view->with('tourOverview', $tourOverview);
        });

        Blade::if('vorstand', fn () => Auth::check() && Auth::user()?->hasVorstandRole());

        if ($this->app->runningUnitTests()) {
            TestingBladeComponentRegistry::register();
            $this->app->booted(static function (): void {
                TestingBladeComponentRegistry::register();
            });
        }

        // Registriert die projektweite Alert-Komponente mit Titel-, Description-, Actions-
        // und Dismiss-Support anstelle der externen Alert-Implementierung.
        Blade::component('alert', Alert::class);

        Livewire::addPersistentMiddleware([
            EnsureAdmin::class,
            EnsureMaddraxikonAdmin::class,
        ]);

        // Override Jetstream Livewire components with maryUI Toast support
        Livewire::component('profile.update-password-form', UpdatePasswordForm::class);
        Livewire::component('profile.logout-other-browser-sessions-form', LogoutOtherBrowserSessionsForm::class);
        Livewire::component('teams.update-team-name-form', UpdateTeamNameForm::class);
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
    }
}

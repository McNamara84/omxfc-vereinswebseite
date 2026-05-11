<?php

namespace App\Providers;

use App\Enums\PollVisibility;
use App\Livewire\Profile\LogoutOtherBrowserSessionsForm;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Livewire\Teams\TeamMemberManager;
use App\Livewire\Teams\UpdateTeamNameForm;
use App\Services\Polls\ActivePollResolver;
use App\Support\Navigation\NavigationBuilder;
use App\View\Components\Alert;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
            $this->app['request']->headers->set('X-Forwarded-Proto', 'https');
            $this->app['request']->headers->set('X-Forwarded-Port', '443');
        }

        // Rate Limiter für Fantreffen-Anmeldung (deaktivierbar via Config für Tests)
        RateLimiter::for('fantreffen-registration', function ($request) {
            if (config('services.fantreffen.disable_rate_limit')) {
                return Limit::none();
            }

            return Limit::perHour(15)->by($request->ip());
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
            $poll = null;

            try {
                $cacheKey = 'polls.active_for_menu.v1';
                $poll = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                    return app(ActivePollResolver::class)->current();
                });
            } catch (QueryException $e) {
                // Table may not exist during tests before migrations run
            }

            $isWithinWindow = $poll ? $poll->isWithinVotingWindow() : false;

            $navigationContext = [
                'activePollMenuLabel' => $poll?->menu_label,
                'showActivePollForAuth' => (bool) ($poll && $isWithinWindow),
                'showActivePollForGuest' => (bool) ($poll && $isWithinWindow && $poll->visibility === PollVisibility::Public),
            ];

            $view->with([
                'activePollForMenu' => $poll,
                'activePollMenuLabel' => $navigationContext['activePollMenuLabel'],
                'showActivePollForAuth' => $navigationContext['showActivePollForAuth'],
                'showActivePollForGuest' => $navigationContext['showActivePollForGuest'],
                'navigation' => app(NavigationBuilder::class)->build(auth()->user(), $navigationContext),
            ]);
        });

        Blade::if('vorstand', fn () => auth()->check() && auth()->user()->hasVorstandRole());

        if ($this->app->runningUnitTests()) {
            Blade::component('testing.components.button', 'button');
            Blade::component('testing.components.badge', 'badge');
            Blade::component('testing.components.checkbox', 'checkbox');
            Blade::component('testing.components.avatar', 'avatar');
            Blade::component('testing.components.file', 'file');
            Blade::component('testing.components.icon', 'icon');
            Blade::component('testing.components.input', 'input');
            Blade::component('testing.components.main', 'main');
            Blade::component('testing.components.mary-modal', 'mary-modal');
            Blade::component('testing.components.mary-modal', 'modal');
            Blade::component('testing.components.password', 'password');
            Blade::component('testing.components.select', 'select');
            Blade::component('testing.components.stat', 'stat');
            Blade::component('testing.components.table', 'table');
            Blade::component('testing.components.theme-toggle', 'theme-toggle');
            Blade::component('testing.components.toast', 'toast');
        }

        // Registriert die projektweite Alert-Komponente mit Titel-, Description-, Actions-
        // und Dismiss-Support anstelle der externen Alert-Implementierung.
        Blade::component('alert', Alert::class);

        // Override Jetstream Livewire components with maryUI Toast support
        Livewire::component('profile.update-password-form', UpdatePasswordForm::class);
        Livewire::component('profile.logout-other-browser-sessions-form', LogoutOtherBrowserSessionsForm::class);
        Livewire::component('teams.update-team-name-form', UpdateTeamNameForm::class);
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
    }
}

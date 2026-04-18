<?php

namespace App\Providers;

use App\Enums\PollVisibility;
use App\Livewire\Profile\LogoutOtherBrowserSessionsForm;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Livewire\Teams\TeamMemberManager;
use App\Livewire\Teams\UpdateTeamNameForm;
use App\Services\Polls\ActivePollResolver;
use App\View\Components\Alert;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production – URL::forceScheme() erzwingt https:// bei der URL-Generierung.
        // Zusätzlich muss X-Forwarded-Proto gesetzt werden, damit request()->isSecure()
        // ebenfalls true liefert. Ohne diesen Header schlägt die Signaturprüfung bei
        // Livewire File-Uploads fehl (401), weil die signierte URL https:// nutzt,
        // aber request()->url() http:// zurückgibt.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            $this->app['request']->headers->set('X-Forwarded-Proto', 'https');
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

            $view->with([
                'activePollForMenu' => $poll,
                'activePollMenuLabel' => $poll?->menu_label,
                'showActivePollForAuth' => (bool) ($poll && $isWithinWindow),
                'showActivePollForGuest' => (bool) ($poll && $isWithinWindow && $poll->visibility === PollVisibility::Public),
            ]);
        });

        Blade::if('vorstand', fn () => auth()->check() && auth()->user()->hasVorstandRole());

        // Override maryUI Alert: adds aria-label="Schließen" to dismiss button (WCAG AA)
        Blade::component('alert', Alert::class);

        // Override Jetstream Livewire components with maryUI Toast support
        Livewire::component('profile.update-password-form', UpdatePasswordForm::class);
        Livewire::component('profile.logout-other-browser-sessions-form', LogoutOtherBrowserSessionsForm::class);
        Livewire::component('teams.update-team-name-form', UpdateTeamNameForm::class);
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
    }
}

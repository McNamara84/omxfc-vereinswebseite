<?php

namespace App\Providers;

use App\Http\Livewire\ContactEmailReveal;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
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
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }

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

        Blade::if('vorstand', fn () => auth()->check() && auth()->user()->hasVorstandRole());

        Livewire::component('contact-email-reveal', ContactEmailReveal::class);
    }
}

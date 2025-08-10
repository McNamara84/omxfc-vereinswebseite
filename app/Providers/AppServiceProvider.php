<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
            try {
                $commit = trim(Process::run(['git', 'rev-list', '--tags', '--max-count=1'])->output());

                if ($commit !== '') {
                    $process = Process::run(['git', 'describe', '--tags', $commit]);
                    $version = $process->successful() ? trim($process->output()) : '0.0.0';
                } else {
                    $version = '0.0.0';
                }
            } catch (\Throwable $e) {
                $version = '0.0.0';
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
    }
}

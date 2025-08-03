<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
                $process = Process::run(['bash', '-c', 'git describe --tags $(git rev-list --tags --max-count=1)']);
                $version = $process->successful() ? trim($process->output()) : '0.0.0';
            } catch (\Throwable $e) {
                $version = '0.0.0';
            }
        }

        View::share('appVersion', $version);
    }
}

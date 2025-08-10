<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Team;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Policies\TeamPolicy;
use App\Policies\BookOfferPolicy;
use App\Policies\BookRequestPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Team::class => TeamPolicy::class,
        BookOffer::class => BookOfferPolicy::class,
        BookRequest::class => BookRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('access-dashboard', function ($user) {
            return $user->hasTeamPermission($user->currentTeam, 'read');
        });
    }
}

<?php

namespace App\Providers;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\KassenbuchEntry;
use App\Models\Poll;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Policies\BookOfferPolicy;
use App\Policies\BookRequestPolicy;
use App\Policies\KassenbuchEntryPolicy;
use App\Policies\PollPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TodoPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        Todo::class => TodoPolicy::class,
        KassenbuchEntry::class => KassenbuchEntryPolicy::class,
        User::class => UserPolicy::class,
        Poll::class => PollPolicy::class,
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

<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Http\Responses\LoginResponse;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Neue LoginResponse-Klasse registrieren
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
            'manage-finances',
            'manage-members',
            'admin',
        ]);

        Jetstream::role('Anwärter', 'Anwärter', [
            // keine besonderen Rechte
        ])->description('Person, die einen Antrag auf Mitgliedschaft gestellt hat.');

        Jetstream::role('Mitglied', 'Mitglied', [
            'read',
        ])->description('Bestätigtes Vereinsmitglied.');

        Jetstream::role('Ehrenmitglied', 'Ehrenmitglied', [
            'read',
        ])->description('Ehrenmitglied mit denselben Zugriffsrechten wie ein reguläres Mitglied.');

        Jetstream::role('Kassenwart', 'Kassenwart', [
            'read',
            'manage-finances',
            'manage-members',
        ])->description('Kassenwart, der Mitgliedsbeiträge und Finanzen verwaltet sowie Mitgliederanträge bestätigen darf.');

        Jetstream::role('Vorstand', 'Vorstand', [
            'create',
            'read',
            'update',
            'delete',
            'manage-finances',
            'manage-members',
        ])->description('Vorstand mit umfassenden administrativen Rechten.');

        Jetstream::role('Admin', 'Admin', [
            'create',
            'read',
            'update',
            'delete',
            'manage-finances',
            'manage-members',
            'admin',
        ])->description('IT-Systemadministrator mit uneingeschränkten Zugriffsrechten auf alle Bereiche.');
    }
}

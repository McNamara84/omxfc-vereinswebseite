<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\BaxxMilestoneActivityService;
use App\Services\VeranstaltungsBaxxService;
use Database\Seeders\VeranstaltungsBaxxPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait VeranstaltungsBaxxPlaywrightSeederTestFixtures
{
    use RefreshDatabase;

    private function seedFixture(string $fixture = 'default'): Veranstaltung
    {
        app(VeranstaltungsBaxxPlaywrightSeeder::class)->run($fixture);

        return Veranstaltung::where('slug', 'baxx-browserpruefung'.($fixture === 'default' ? '' : '-'.$fixture))->firstOrFail();
    }

    private function archive(Veranstaltung $event): void
    {
        $team = Team::membersTeam();
        $actor = User::factory()->create(['current_team_id' => $team->id]);
        $actor->teams()->attach($team->id, ['role' => Role::Admin->value]);
        $service = app(VeranstaltungsBaxxService::class);
        $service->setTeilnahme($event, $event->anmeldungen()->orderBy('id')->firstOrFail()->id, true, $actor);
        $service->speichern($event, ['status' => 'archiviert', 'teilnahme_baxx' => 23], $actor);
        app(BaxxMilestoneActivityService::class)->recordForUserPoint($event->baxxVergaben()->firstOrFail()->id);
    }
}

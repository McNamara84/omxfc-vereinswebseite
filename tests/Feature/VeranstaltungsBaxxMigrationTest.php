<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VeranstaltungsBaxxMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_only_existing_archives_and_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_09_27_120000_add_veranstaltungs_baxx.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('user_points', 'veranstaltung_id'));
        $this->assertFalse(Schema::hasColumn('veranstaltungen', 'baxx_status'));
        $this->assertFalse(Schema::hasColumn('fantreffen_anmeldungen', 'teilgenommen'));

        $draftId = DB::table('veranstaltungen')->insertGetId(['titel' => 'Entwurf', 'slug' => 'migration-entwurf', 'status' => 'entwurf']);
        $eventId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');
        $registrationId = DB::table('fantreffen_anmeldungen')->insertGetId([
            'veranstaltung_id' => $eventId, 'email' => 'migration@example.com', 'ist_mitglied' => true,
        ]);
        $user = User::factory()->create();
        $pointId = DB::table('user_points')->insertGetId(['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 7]);
        $count = DB::table('user_points')->count();

        $migration->up();

        $this->assertDatabaseHas('veranstaltungen', [
            'slug' => 'maddrax-fantreffen-2026', 'baxx_status' => 'bestand_ausgeschlossen',
            'teilnahme_baxx' => 10, 'baxx_abgeschlossen_am' => null, 'baxx_abgeschlossen_von' => null,
        ]);
        foreach ([$eventId, $draftId] as $id) {
            $this->assertDatabaseHas('veranstaltungen', ['id' => $id, 'teilnahme_baxx' => 10, 'baxx_status' => 'offen']);
        }
        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'id' => $registrationId, 'teilgenommen' => false, 'teilnahme_bestaetigt_am' => null, 'teilnahme_bestaetigt_von' => null,
        ]);
        $this->assertDatabaseHas('user_points', ['id' => $pointId, 'points' => 7, 'veranstaltung_id' => null]);
        $this->assertSame($count, DB::table('user_points')->count());
    }

    public function test_event_with_credits_cannot_be_deleted(): void
    {
        $eventId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');
        DB::table('user_points')->insert([
            'user_id' => User::factory()->create()->id, 'team_id' => Team::membersTeam()->id,
            'points' => 10, 'veranstaltung_id' => $eventId,
        ]);
        $this->expectException(QueryException::class);
        DB::table('veranstaltungen')->where('id', $eventId)->delete();
    }

    public function test_deleting_the_actor_keeps_attendance_and_completion(): void
    {
        $actorId = User::factory()->create()->id;
        $eventId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');
        DB::table('veranstaltungen')->where('id', $eventId)->update([
            'baxx_status' => 'abgeschlossen', 'baxx_abgeschlossen_von' => $actorId, 'baxx_abgeschlossen_am' => now(),
        ]);
        $registrationId = DB::table('fantreffen_anmeldungen')->insertGetId([
            'veranstaltung_id' => $eventId, 'email' => 'audit@example.com', 'teilgenommen' => true,
            'teilnahme_bestaetigt_am' => now(), 'teilnahme_bestaetigt_von' => $actorId,
        ]);
        DB::table('users')->where('id', $actorId)->delete();
        $this->assertDatabaseHas('veranstaltungen', ['id' => $eventId, 'baxx_status' => 'abgeschlossen', 'baxx_abgeschlossen_von' => null]);
        $this->assertDatabaseHas('fantreffen_anmeldungen', ['id' => $registrationId, 'teilgenommen' => true, 'teilnahme_bestaetigt_von' => null]);
    }
}

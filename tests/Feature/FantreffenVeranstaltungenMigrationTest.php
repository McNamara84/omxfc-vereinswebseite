<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FantreffenVeranstaltungenMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_up_handles_partially_applied_fantreffen_state(): void
    {
        $this->dropIndexIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_veranstaltung_id_email_unique', true);
        $this->dropIndexIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_veranstaltung_id_user_id_unique', true);
        $this->dropIndexIfExists('fantreffen_anmeldungen', 'fantreffen_anmeldungen_user_id_index');

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->unique('email', 'fantreffen_anmeldungen_email_unique');
            $table->unique('user_id', 'fantreffen_anmeldungen_user_id_unique');
        });

        $migration = require database_path('migrations/2026_05_10_100200_link_fantreffen_data_to_veranstaltungen.php');
        $migration->up();

        $user = User::factory()->create();
        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');
        $jubilaeumEventId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');

        DB::table('fantreffen_anmeldungen')->insert([
            'veranstaltung_id' => $archivEventId,
            'user_id' => $user->id,
            'email' => 'fan@example.test',
            'vorname' => 'Maddrax',
            'nachname' => 'Fan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fantreffen_anmeldungen')->insert([
            'veranstaltung_id' => $jubilaeumEventId,
            'user_id' => $user->id,
            'email' => 'fan@example.test',
            'vorname' => 'Maddrax',
            'nachname' => 'Fan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('fantreffen_anmeldungen', 2);
    }

    private function dropIndexIfExists(string $tableName, string $indexName, bool $unique = false): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName, $unique) {
                if ($unique) {
                    $table->dropUnique($indexName);

                    return;
                }

                $table->dropIndex($indexName);
            });
        } catch (\Throwable) {
        }
    }
}

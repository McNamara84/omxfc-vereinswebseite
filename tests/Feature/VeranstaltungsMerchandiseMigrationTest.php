<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VeranstaltungsMerchandiseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_uses_mysql_safe_foreign_key_names(): void
    {
        $migration = require database_path('migrations/2026_05_16_120000_add_veranstaltungs_merchandise_tables.php');

        $constraintNames = [
            $migration::FOREIGN_KEY_MERCHVARIANTEN_ARTIKEL,
            $migration::FOREIGN_KEY_MERCHBESTELLUNG_ANMELDUNG,
            $migration::FOREIGN_KEY_MERCHBESTELLUNG_ARTIKEL,
            $migration::FOREIGN_KEY_MERCHBESTELLUNG_VARIANTE,
        ];

        foreach ($constraintNames as $constraintName) {
            $this->assertLessThanOrEqual(64, strlen($constraintName));
        }
    }

    public function test_migration_converts_legacy_tshirt_configuration_and_orders_into_merchandise(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('fantreffen_anmeldung_merchartikel');
        Schema::dropIfExists('veranstaltungs_merchvarianten');
        Schema::dropIfExists('veranstaltungs_merchartikel');
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('veranstaltungen', 'merch_deadline')) {
            Schema::table('veranstaltungen', function ($table) {
                $table->dropColumn('merch_deadline');
            });
        }

        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');
        $jubilaeumEventId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');

        DB::table('fantreffen_anmeldungen')->insert([
            'veranstaltung_id' => $archivEventId,
            'vorname' => 'Legacy',
            'nachname' => 'Fan',
            'email' => 'legacy@example.test',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
            'tshirt_fertig' => true,
            'payment_status' => 'paid',
            'payment_amount' => 25,
            'zahlungseingang' => true,
            'ist_mitglied' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_05_16_120000_add_veranstaltungs_merchandise_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('veranstaltungen', 'merch_deadline'));
        $this->assertDatabaseHas('veranstaltungen', [
            'id' => $archivEventId,
            'merch_deadline' => '2026-02-28 23:59:59',
        ]);

        $artikelId = DB::table('veranstaltungs_merchartikel')
            ->where('veranstaltung_id', $archivEventId)
            ->where('bezeichnung', 'T-Shirt')
            ->value('id');

        $this->assertNotNull($artikelId);
        $this->assertDatabaseHas('veranstaltungs_merchartikel', [
            'id' => $artikelId,
            'preis' => 25,
            'is_active' => true,
        ]);

        $this->assertSame(7, DB::table('veranstaltungs_merchvarianten')
            ->where('veranstaltungs_merchartikel_id', $artikelId)
            ->count());

        $varianteId = DB::table('veranstaltungs_merchvarianten')
            ->where('veranstaltungs_merchartikel_id', $artikelId)
            ->where('bezeichnung', 'L')
            ->value('id');

        $anmeldungId = DB::table('fantreffen_anmeldungen')->where('email', 'legacy@example.test')->value('id');

        $this->assertDatabaseHas('fantreffen_anmeldung_merchartikel', [
            'fantreffen_anmeldung_id' => $anmeldungId,
            'veranstaltungs_merchartikel_id' => $artikelId,
            'veranstaltungs_merchvariante_id' => $varianteId,
            'preis_zum_bestellzeitpunkt' => 25,
            'status_erledigt' => true,
        ]);

        $this->assertSame(0, DB::table('veranstaltungs_merchartikel')
            ->where('veranstaltung_id', $jubilaeumEventId)
            ->count());
    }

    public function test_migration_up_handles_partially_applied_merchandise_tables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('fantreffen_anmeldung_merchartikel');
        Schema::dropIfExists('veranstaltungs_merchvarianten');
        Schema::dropIfExists('veranstaltungs_merchartikel');
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('veranstaltungen', 'merch_deadline')) {
            Schema::table('veranstaltungen', function (Blueprint $table) {
                $table->dropColumn('merch_deadline');
            });
        }

        Schema::create('veranstaltungs_merchartikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veranstaltung_id')->constrained('veranstaltungen')->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->text('beschreibung')->nullable();
            $table->decimal('preis', 8, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['veranstaltung_id', 'sort_order'], 'veranstaltungs_merchartikel_veranstaltung_sort_index');
        });

        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');

        $anmeldungId = DB::table('fantreffen_anmeldungen')->insertGetId([
            'veranstaltung_id' => $archivEventId,
            'vorname' => 'Legacy',
            'nachname' => 'Fan',
            'email' => 'partial@example.test',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'XL',
            'tshirt_fertig' => false,
            'payment_status' => 'paid',
            'payment_amount' => 25,
            'zahlungseingang' => true,
            'ist_mitglied' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $artikelId = DB::table('veranstaltungs_merchartikel')->insertGetId([
            'veranstaltung_id' => $archivEventId,
            'bezeichnung' => 'T-Shirt',
            'beschreibung' => null,
            'preis' => 25,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $migration = require database_path('migrations/2026_05_16_120000_add_veranstaltungs_merchandise_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('veranstaltungs_merchvarianten'));
        $this->assertTrue(Schema::hasTable('fantreffen_anmeldung_merchartikel'));
        $this->assertTrue(Schema::hasColumn('veranstaltungen', 'merch_deadline'));

        $this->assertSame(1, DB::table('veranstaltungs_merchartikel')
            ->where('veranstaltung_id', $archivEventId)
            ->where('bezeichnung', 'T-Shirt')
            ->count());

        $this->assertSame(7, DB::table('veranstaltungs_merchvarianten')
            ->where('veranstaltungs_merchartikel_id', $artikelId)
            ->count());

        $varianteId = DB::table('veranstaltungs_merchvarianten')
            ->where('veranstaltungs_merchartikel_id', $artikelId)
            ->where('bezeichnung', 'XL')
            ->value('id');

        $this->assertDatabaseHas('fantreffen_anmeldung_merchartikel', [
            'fantreffen_anmeldung_id' => $anmeldungId,
            'veranstaltungs_merchartikel_id' => $artikelId,
            'veranstaltungs_merchvariante_id' => $varianteId,
            'preis_zum_bestellzeitpunkt' => 25,
            'status_erledigt' => false,
        ]);
    }

    public function test_migration_up_handles_preexisting_merch_order_table(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('fantreffen_anmeldung_merchartikel');
        Schema::dropIfExists('veranstaltungs_merchvarianten');
        Schema::dropIfExists('veranstaltungs_merchartikel');
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('veranstaltungen', 'merch_deadline')) {
            Schema::table('veranstaltungen', function (Blueprint $table) {
                $table->dropColumn('merch_deadline');
            });
        }

        Schema::create('fantreffen_anmeldung_merchartikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fantreffen_anmeldung_id');
            $table->foreignId('veranstaltungs_merchartikel_id');
            $table->foreignId('veranstaltungs_merchvariante_id')->nullable();
            $table->decimal('preis_zum_bestellzeitpunkt', 8, 2);
            $table->boolean('status_erledigt')->default(false);
            $table->timestamp('status_erledigt_am')->nullable();
            $table->timestamps();

            $table->unique(
                ['fantreffen_anmeldung_id', 'veranstaltungs_merchartikel_id'],
                'fantreffen_anmeldung_merch_unique'
            );
        });

        $archivEventId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');

        $anmeldungId = DB::table('fantreffen_anmeldungen')->insertGetId([
            'veranstaltung_id' => $archivEventId,
            'vorname' => 'Retry',
            'nachname' => 'Fan',
            'email' => 'retry@example.test',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'M',
            'tshirt_fertig' => false,
            'payment_status' => 'paid',
            'payment_amount' => 25,
            'zahlungseingang' => true,
            'ist_mitglied' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_05_16_120000_add_veranstaltungs_merchandise_tables.php');
        $migration->up();

        $artikelId = DB::table('veranstaltungs_merchartikel')
            ->where('veranstaltung_id', $archivEventId)
            ->where('bezeichnung', 'T-Shirt')
            ->value('id');

        $varianteId = DB::table('veranstaltungs_merchvarianten')
            ->where('veranstaltungs_merchartikel_id', $artikelId)
            ->where('bezeichnung', 'M')
            ->value('id');

        $this->assertNotNull($artikelId);
        $this->assertNotNull($varianteId);
        $this->assertSame(1, DB::table('fantreffen_anmeldung_merchartikel')->count());

        $this->assertDatabaseHas('fantreffen_anmeldung_merchartikel', [
            'fantreffen_anmeldung_id' => $anmeldungId,
            'veranstaltungs_merchartikel_id' => $artikelId,
            'veranstaltungs_merchvariante_id' => $varianteId,
            'preis_zum_bestellzeitpunkt' => 25,
            'status_erledigt' => false,
        ]);
    }
}

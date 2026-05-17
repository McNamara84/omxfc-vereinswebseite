<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_TSHIRT_VARIANTEN = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    public const FOREIGN_KEY_MERCHVARIANTEN_ARTIKEL = 'vmv_artikel_fk';

    public const FOREIGN_KEY_MERCHBESTELLUNG_ANMELDUNG = 'fam_merch_anm_fk';

    public const FOREIGN_KEY_MERCHBESTELLUNG_ARTIKEL = 'fam_merch_art_fk';

    public const FOREIGN_KEY_MERCHBESTELLUNG_VARIANTE = 'fam_merch_var_fk';

    public function up(): void
    {
        if (! Schema::hasColumn('veranstaltungen', 'merch_deadline')) {
            Schema::table('veranstaltungen', function (Blueprint $table) {
                $table->timestamp('merch_deadline')->nullable()->after('tshirt_deadline');
            });
        }

        if (! Schema::hasTable('veranstaltungs_merchartikel')) {
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
        }

        if (! Schema::hasTable('veranstaltungs_merchvarianten')) {
            Schema::create('veranstaltungs_merchvarianten', function (Blueprint $table) {
                $table->id();
                $table->foreignId('veranstaltungs_merchartikel_id');
                $table->string('bezeichnung');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('veranstaltungs_merchartikel_id', self::FOREIGN_KEY_MERCHVARIANTEN_ARTIKEL)
                    ->references('id')
                    ->on('veranstaltungs_merchartikel')
                    ->cascadeOnDelete();

                $table->unique(
                    ['veranstaltungs_merchartikel_id', 'bezeichnung'],
                    'veranstaltungs_merchvarianten_artikel_bezeichnung_unique'
                );
            });
        }

        $this->ensureMySqlForeignKey(
            'veranstaltungs_merchvarianten',
            'veranstaltungs_merchartikel_id',
            self::FOREIGN_KEY_MERCHVARIANTEN_ARTIKEL,
            'veranstaltungs_merchartikel',
            'cascade'
        );

        if (! Schema::hasTable('fantreffen_anmeldung_merchartikel')) {
            Schema::create('fantreffen_anmeldung_merchartikel', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fantreffen_anmeldung_id');
                $table->foreignId('veranstaltungs_merchartikel_id');
                $table->foreignId('veranstaltungs_merchvariante_id')->nullable();
                $table->decimal('preis_zum_bestellzeitpunkt', 8, 2);
                $table->boolean('status_erledigt')->default(false);
                $table->timestamp('status_erledigt_am')->nullable();
                $table->timestamps();

                $table->foreign('fantreffen_anmeldung_id', self::FOREIGN_KEY_MERCHBESTELLUNG_ANMELDUNG)
                    ->references('id')
                    ->on('fantreffen_anmeldungen')
                    ->cascadeOnDelete();

                $table->foreign('veranstaltungs_merchartikel_id', self::FOREIGN_KEY_MERCHBESTELLUNG_ARTIKEL)
                    ->references('id')
                    ->on('veranstaltungs_merchartikel')
                    ->cascadeOnDelete();

                $table->foreign('veranstaltungs_merchvariante_id', self::FOREIGN_KEY_MERCHBESTELLUNG_VARIANTE)
                    ->references('id')
                    ->on('veranstaltungs_merchvarianten')
                    ->nullOnDelete();

                $table->unique(
                    ['fantreffen_anmeldung_id', 'veranstaltungs_merchartikel_id'],
                    'fantreffen_anmeldung_merch_unique'
                );
            });
        }

        $this->ensureMySqlForeignKey(
            'fantreffen_anmeldung_merchartikel',
            'fantreffen_anmeldung_id',
            self::FOREIGN_KEY_MERCHBESTELLUNG_ANMELDUNG,
            'fantreffen_anmeldungen',
            'cascade'
        );

        $this->ensureMySqlForeignKey(
            'fantreffen_anmeldung_merchartikel',
            'veranstaltungs_merchartikel_id',
            self::FOREIGN_KEY_MERCHBESTELLUNG_ARTIKEL,
            'veranstaltungs_merchartikel',
            'cascade'
        );

        $this->ensureMySqlForeignKey(
            'fantreffen_anmeldung_merchartikel',
            'veranstaltungs_merchvariante_id',
            self::FOREIGN_KEY_MERCHBESTELLUNG_VARIANTE,
            'veranstaltungs_merchvarianten',
            'set null'
        );

        DB::table('veranstaltungen')
            ->whereNull('merch_deadline')
            ->whereNotNull('tshirt_deadline')
            ->update(['merch_deadline' => DB::raw('tshirt_deadline')]);

        $veranstaltungen = DB::table('veranstaltungen')
            ->select('id', 'tshirt_aktiv', 'tshirt_preis')
            ->orderBy('id')
            ->get();

        foreach ($veranstaltungen as $veranstaltung) {
            $hasLegacyOrders = DB::table('fantreffen_anmeldungen')
                ->where('veranstaltung_id', $veranstaltung->id)
                ->where('tshirt_bestellt', true)
                ->exists();

            if (! $veranstaltung->tshirt_aktiv && ! $hasLegacyOrders) {
                continue;
            }

            $timestamp = now();
            $artikelId = DB::table('veranstaltungs_merchartikel')
                ->where('veranstaltung_id', $veranstaltung->id)
                ->where('bezeichnung', 'T-Shirt')
                ->value('id');

            if ($artikelId === null) {
                $artikelId = DB::table('veranstaltungs_merchartikel')->insertGetId([
                    'veranstaltung_id' => $veranstaltung->id,
                    'bezeichnung' => 'T-Shirt',
                    'beschreibung' => null,
                    'preis' => $veranstaltung->tshirt_preis ?? 25.00,
                    'sort_order' => 0,
                    'is_active' => (bool) $veranstaltung->tshirt_aktiv,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            } else {
                DB::table('veranstaltungs_merchartikel')
                    ->where('id', $artikelId)
                    ->update([
                        'beschreibung' => null,
                        'preis' => $veranstaltung->tshirt_preis ?? 25.00,
                        'sort_order' => 0,
                        'is_active' => (bool) $veranstaltung->tshirt_aktiv,
                        'updated_at' => $timestamp,
                    ]);
            }

            $variantenMap = [];

            foreach (self::DEFAULT_TSHIRT_VARIANTEN as $index => $bezeichnung) {
                DB::table('veranstaltungs_merchvarianten')->updateOrInsert(
                    [
                        'veranstaltungs_merchartikel_id' => $artikelId,
                        'bezeichnung' => $bezeichnung,
                    ],
                    [
                        'sort_order' => $index,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );

                $varianteId = DB::table('veranstaltungs_merchvarianten')
                    ->where('veranstaltungs_merchartikel_id', $artikelId)
                    ->where('bezeichnung', $bezeichnung)
                    ->value('id');

                $variantenMap[$bezeichnung] = $varianteId;
            }

            $anmeldungen = DB::table('fantreffen_anmeldungen')
                ->select('id', 'tshirt_groesse', 'tshirt_fertig', 'updated_at')
                ->where('veranstaltung_id', $veranstaltung->id)
                ->where('tshirt_bestellt', true)
                ->orderBy('id')
                ->get();

            foreach ($anmeldungen as $anmeldung) {
                DB::table('fantreffen_anmeldung_merchartikel')->updateOrInsert(
                    [
                        'fantreffen_anmeldung_id' => $anmeldung->id,
                        'veranstaltungs_merchartikel_id' => $artikelId,
                    ],
                    [
                        'veranstaltungs_merchvariante_id' => $anmeldung->tshirt_groesse
                            ? ($variantenMap[$anmeldung->tshirt_groesse] ?? null)
                            : null,
                        'preis_zum_bestellzeitpunkt' => $veranstaltung->tshirt_preis ?? 25.00,
                        'status_erledigt' => (bool) $anmeldung->tshirt_fertig,
                        'status_erledigt_am' => $anmeldung->tshirt_fertig ? $anmeldung->updated_at : null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fantreffen_anmeldung_merchartikel');
        Schema::dropIfExists('veranstaltungs_merchvarianten');
        Schema::dropIfExists('veranstaltungs_merchartikel');

        if (Schema::hasColumn('veranstaltungen', 'merch_deadline')) {
            Schema::table('veranstaltungen', function (Blueprint $table) {
                $table->dropColumn('merch_deadline');
            });
        }
    }

    private function ensureMySqlForeignKey(
        string $table,
        string $column,
        string $constraint,
        string $referencesTable,
        string $onDelete
    ): void {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $constraintExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($constraintExists) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`) ON DELETE %s',
            $table,
            $constraint,
            $column,
            $referencesTable,
            strtoupper($onDelete)
        ));
    }
};
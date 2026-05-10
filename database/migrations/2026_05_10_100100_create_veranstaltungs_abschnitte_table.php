<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veranstaltungs_abschnitte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veranstaltung_id')->constrained('veranstaltungen')->cascadeOnDelete();
            $table->string('schluessel')->nullable();
            $table->string('titel');
            $table->longText('markdown_inhalt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        $jubilaeumId = DB::table('veranstaltungen')->where('slug', 'jubilaeumsfeier-band-700')->value('id');
        $fantreffenId = DB::table('veranstaltungen')->where('slug', 'maddrax-fantreffen-2026')->value('id');

        if ($jubilaeumId) {
            DB::table('veranstaltungs_abschnitte')->insert([
                [
                    'veranstaltung_id' => $jubilaeumId,
                    'schluessel' => 'programm',
                    'titel' => 'Was bisher feststeht',
                    'markdown_inhalt' => "- Datum: 14. November 2026\n- Anlass: Jubiläumsfeier zu Band 700\n- Diese Inhalte können im Admin-Bereich ergänzt oder umgestellt werden.",
                    'sort_order' => 1,
                    'is_visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'veranstaltung_id' => $jubilaeumId,
                    'schluessel' => 'hinweise',
                    'titel' => 'Hinweise zur Anmeldung',
                    'markdown_inhalt' => "Die Anmeldung läuft veranstaltungsbezogen. Daten aus früheren Veranstaltungen werden nicht übernommen.",
                    'sort_order' => 2,
                    'is_visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if ($fantreffenId) {
            DB::table('veranstaltungs_abschnitte')->insert([
                [
                    'veranstaltung_id' => $fantreffenId,
                    'schluessel' => 'archiv',
                    'titel' => 'Archivhinweis',
                    'markdown_inhalt' => "Diese Seite dokumentiert das Fantreffen 2026 im Archiv. Neue Anmeldungen sind geschlossen.",
                    'sort_order' => 1,
                    'is_visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('veranstaltungs_abschnitte');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veranstaltungen', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->string('slug')->unique();
            $table->enum('status', ['entwurf', 'veroeffentlicht', 'archiviert'])->default('entwurf');
            $table->string('veranstaltungsart')->nullable();
            $table->string('untertitel')->nullable();
            $table->text('teaser')->nullable();
            $table->longText('beschreibung')->nullable();
            $table->timestamp('datum_von')->nullable();
            $table->timestamp('datum_bis')->nullable();
            $table->string('ort_name')->nullable();
            $table->text('ort_adresse')->nullable();
            $table->string('maps_url')->nullable();
            $table->boolean('anmeldung_aktiv')->default(false);
            $table->timestamp('anmeldung_start')->nullable();
            $table->timestamp('anmeldung_ende')->nullable();
            $table->boolean('zahlung_aktiv')->default(false);
            $table->boolean('tshirt_aktiv')->default(false);
            $table->timestamp('tshirt_deadline')->nullable();
            $table->boolean('vip_autoren_aktiv')->default(false);
            $table->decimal('gastgebuehr', 8, 2)->default(5.00);
            $table->decimal('tshirt_preis', 8, 2)->default(25.00);
            $table->string('benachrichtigungs_email')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('ist_highlight')->default(false);
            $table->timestamps();
        });

        DB::table('veranstaltungen')->insert([
            'titel' => 'Maddrax-Fantreffen 2026',
            'slug' => 'maddrax-fantreffen-2026',
            'status' => 'archiviert',
            'veranstaltungsart' => 'Fantreffen',
            'untertitel' => 'Archivierte Veranstaltung',
            'teaser' => 'Das Fantreffen 2026 hat bereits stattgefunden. Die Informationen bleiben als Archiv erhalten.',
            'beschreibung' => "Das Maddrax-Fantreffen 2026 fand am 9. Mai 2026 statt.\n\nDie Anmeldedaten bleiben im System archiviert und sind intern weiter auswertbar.",
            'datum_von' => '2026-05-09 19:00:00',
            'datum_bis' => null,
            'ort_name' => "L'Osteria Köln Mülheim",
            'ort_adresse' => 'Düsseldorfer Str. 1-3, 51063 Köln',
            'maps_url' => 'https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5',
            'anmeldung_aktiv' => false,
            'anmeldung_start' => null,
            'anmeldung_ende' => null,
            'zahlung_aktiv' => true,
            'tshirt_aktiv' => true,
            'tshirt_deadline' => '2026-02-28 23:59:59',
            'vip_autoren_aktiv' => true,
            'gastgebuehr' => 5.00,
            'tshirt_preis' => 25.00,
            'benachrichtigungs_email' => 'vorstand@maddrax-fanclub.de',
            'seo_title' => 'Maddrax-Fantreffen 2026 Archiv',
            'seo_description' => 'Archivseite des Maddrax-Fantreffens 2026.',
            'sort_order' => 1,
            'ist_highlight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('veranstaltungen')->insert([
            'titel' => 'Jubiläumsfeier zu Band 700',
            'slug' => 'jubilaeumsfeier-band-700',
            'status' => 'veroeffentlicht',
            'veranstaltungsart' => 'Jubiläumsfeier',
            'untertitel' => 'Das nächste Community-Treffen des OMXFC',
            'teaser' => 'Am 14. November 2026 feiern wir gemeinsam Band 700 der Maddrax-Serie.',
            'beschreibung' => "Die nächste große Community-Veranstaltung steht bereits fest: Am 14. November 2026 feiern wir gemeinsam Band 700 der Maddrax-Serie.\n\nAlle Details dieser Seite können künftig direkt im Admin-Bereich gepflegt werden.",
            'datum_von' => '2026-11-14 18:00:00',
            'datum_bis' => null,
            'ort_name' => 'Wird im Admin-Bereich gepflegt',
            'ort_adresse' => 'Ort und Ablauf können flexibel ergänzt werden.',
            'maps_url' => null,
            'anmeldung_aktiv' => true,
            'anmeldung_start' => null,
            'anmeldung_ende' => null,
            'zahlung_aktiv' => false,
            'tshirt_aktiv' => false,
            'tshirt_deadline' => null,
            'vip_autoren_aktiv' => false,
            'gastgebuehr' => 0.00,
            'tshirt_preis' => 25.00,
            'benachrichtigungs_email' => 'vorstand@maddrax-fanclub.de',
            'seo_title' => 'Jubiläumsfeier zu Band 700',
            'seo_description' => 'Infos und Anmeldung zur Jubiläumsfeier zu Band 700 der Maddrax-Serie.',
            'sort_order' => 0,
            'ist_highlight' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('veranstaltungen');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Die 39 Statistik-Cards mit explizitem Legacy-Slug-Mapping.
     *
     * legacy_slug = Str::slug() des alten Titels aus config/rewards.php,
     * wie er vom RewardSeeder erzeugt wurde. Damit matchen wir bestehende
     * Rewards zuverlässig per Slug statt per Beschreibung.
     */
    private function getStatisticSections(): array
    {
        return [
            ['id' => 'author-chart', 'legacy_slug' => 'statistik-maddrax-romane-je-autorin', 'label' => 'Maddrax-Romane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl Maddrax-Romane je Autor:in.'],
            ['id' => 'teamplayer', 'legacy_slug' => 'statistik-top-teamplayer', 'label' => 'Top Teamplayer', 'description' => 'Schaltet Statistik zu Autor:innen frei, die häufig gemeinsam mit anderen Autor:innen MADDRAX-Romane schreiben.'],
            ['id' => 'top-romane', 'legacy_slug' => 'statistik-top-maddrax-romane', 'label' => 'Top 10 Maddrax-Romane', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Romanen im Maddraxikon frei.'],
            ['id' => 'top-autoren', 'legacy_slug' => 'statistik-top-maddrax-autorinnen', 'label' => 'Top 10 Autor:innen nach Ø-Bewertung', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Autoren frei.'],
            ['id' => 'top-charaktere', 'legacy_slug' => 'statistik-top-maddrax-charaktere', 'label' => 'Top 10 Charaktere nach Auftritten', 'description' => 'Schaltet die Statistik zu den am häufigsten auftretenden MADDRAX-Charakteren im Maddraxikon frei.'],
            ['id' => 'maddraxikon-bewertungen', 'legacy_slug' => 'statistik-bewertungen-im-maddraxikon', 'label' => 'Bewertungen im Maddraxikon', 'description' => 'Zeigt Durchschnittsbewertung, Stimmenanzahl und Ø-Stimmen pro Roman.'],
            ['id' => 'mitglieds-rezensionen', 'legacy_slug' => 'statistik-rezensionen-unserer-mitglieder', 'label' => 'Rezensionen unserer Mitglieder', 'description' => 'Zeigt Analysen rund um die Rezensionen im Verein.'],
            ['id' => 'zyklus-euree', 'legacy_slug' => 'statistik-bewertungen-des-euree-zyklus', 'label' => 'Bewertungen des Euree-Zyklus', 'description' => 'Zeigt Bewertungen des Euree-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-meeraka', 'legacy_slug' => 'statistik-bewertungen-des-meeraka-zyklus', 'label' => 'Bewertungen des Meeraka-Zyklus', 'description' => 'Zeigt Bewertungen des Meeraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-expedition', 'legacy_slug' => 'statistik-bewertungen-des-expeditions-zyklus', 'label' => 'Bewertungen des Expeditions-Zyklus', 'description' => 'Zeigt Bewertungen des Expeditions-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-kratersee', 'legacy_slug' => 'statistik-bewertungen-des-kratersee-zyklus', 'label' => 'Bewertungen des Kratersee-Zyklus', 'description' => 'Zeigt Bewertungen des Kratersee-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-daamuren', 'legacy_slug' => 'statistik-bewertungen-des-daamuren-zyklus', 'label' => "Bewertungen des Daa'muren-Zyklus", 'description' => "Zeigt Bewertungen des Daa'muren-Zyklus aus dem Maddraxikon in einem Liniendiagramm."],
            ['id' => 'zyklus-wandler', 'legacy_slug' => 'statistik-bewertungen-des-wandler-zyklus', 'label' => 'Bewertungen des Wandler-Zyklus', 'description' => 'Zeigt Bewertungen des Wandler-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-mars', 'legacy_slug' => 'statistik-bewertungen-des-mars-zyklus', 'label' => 'Bewertungen des Mars-Zyklus', 'description' => 'Zeigt Bewertungen des Mars-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ausala', 'legacy_slug' => 'statistik-bewertungen-des-ausala-zyklus', 'label' => 'Bewertungen des Ausala-Zyklus', 'description' => 'Zeigt Bewertungen des Ausala-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-afra', 'legacy_slug' => 'statistik-bewertungen-des-afra-zyklus', 'label' => 'Bewertungen des Afra-Zyklus', 'description' => 'Zeigt Bewertungen des Afra-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-antarktis', 'legacy_slug' => 'statistik-bewertungen-des-antarktis-zyklus', 'label' => 'Bewertungen des Antarktis-Zyklus', 'description' => 'Zeigt Bewertungen des Antarktis-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-schatten', 'legacy_slug' => 'statistik-bewertungen-des-schatten-zyklus', 'label' => 'Bewertungen des Schatten-Zyklus', 'description' => 'Zeigt Bewertungen des Schatten-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ursprung', 'legacy_slug' => 'statistik-bewertungen-des-ursprung-zyklus', 'label' => 'Bewertungen des Ursprung-Zyklus', 'description' => 'Zeigt Bewertungen des Ursprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-streiter', 'legacy_slug' => 'statistik-bewertungen-des-streiter-zyklus', 'label' => 'Bewertungen des Streiter-Zyklus', 'description' => 'Zeigt Bewertungen des Streiter-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-archivar', 'legacy_slug' => 'statistik-bewertungen-des-archivar-zyklus', 'label' => 'Bewertungen des Archivar-Zyklus', 'description' => 'Zeigt Bewertungen des Archivar-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-zeitsprung', 'legacy_slug' => 'statistik-bewertungen-des-zeitsprung-zyklus', 'label' => 'Bewertungen des Zeitsprung-Zyklus', 'description' => 'Zeigt Bewertungen des Zeitsprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-fremdwelt', 'legacy_slug' => 'statistik-bewertungen-des-fremdwelt-zyklus', 'label' => 'Bewertungen des Fremdwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Fremdwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-parallelwelt', 'legacy_slug' => 'statistik-bewertungen-des-parallelwelt-zyklus', 'label' => 'Bewertungen des Parallelwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Parallelwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltenriss', 'legacy_slug' => 'statistik-bewertungen-des-weltenriss-zyklus', 'label' => 'Bewertungen des Weltenriss-Zyklus', 'description' => 'Zeigt Bewertungen des Weltenriss-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-amraka', 'legacy_slug' => 'statistik-bewertungen-des-amraka-zyklus', 'label' => 'Bewertungen des Amraka-Zyklus', 'description' => 'Zeigt Bewertungen des Amraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltrat', 'legacy_slug' => 'statistik-bewertungen-des-weltrat-zyklus', 'label' => 'Bewertungen des Weltrat-Zyklus', 'description' => 'Zeigt Bewertungen des Weltrat-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-die-abenteurer-heftromane', 'label' => 'Bewertungen der Die Abenteurer-Heftromane', 'description' => 'Zeigt Bewertungen der Die Abenteurer-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-autoren', 'legacy_slug' => 'statistik-die-abenteurer-heftromane-je-autorin', 'label' => 'Die Abenteurer-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Die Abenteurer-Heftromane je Autor:in.'],
            ['id' => 'hardcover-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-hardcover', 'label' => 'Bewertungen der Hardcover', 'description' => 'Zeigt Bewertungen der Hardcover aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'hardcover-autoren', 'legacy_slug' => 'statistik-maddrax-hardcover-je-autorin', 'label' => 'Maddrax-Hardcover je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Maddrax-Hardcover je Autor:in.'],
            ['id' => 'top-themen', 'legacy_slug' => 'statistik-top20-maddrax-themen', 'label' => 'TOP20 Maddrax-Themen', 'description' => 'Zeigt die 20 am besten bewerteten MADDRAX-Themen im Maddraxikon.'],
            ['id' => 'mission-mars-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-mission-mars-heftromane', 'label' => 'Bewertungen der Mission Mars-Heftromane', 'description' => 'Zeigt Bewertungen der Mission Mars-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'mission-mars-autoren', 'legacy_slug' => 'statistik-mission-mars-heftromane-je-autorin', 'label' => 'Mission Mars-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Mission Mars-Heftromane je Autor:in.'],
            ['id' => 'volk-der-tiefe-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-das-volk-der-tiefe-heftromane', 'label' => 'Bewertungen der Das Volk der Tiefe-Heftromane', 'description' => 'Zeigt Bewertungen der Das Volk der Tiefe-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'volk-der-tiefe-autoren', 'legacy_slug' => 'statistik-das-volk-der-tiefe-heftromane-je-autorin', 'label' => 'Das Volk der Tiefe-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Das Volk der Tiefe-Heftromane je Autor:in.'],
            ['id' => 'zweitausendzwoelf-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-2012-heftromane', 'label' => 'Bewertungen der 2012-Heftromane', 'description' => 'Zeigt Bewertungen der 2012-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zweitausendzwoelf-autoren', 'legacy_slug' => 'statistik-2012-heftromane-je-autorin', 'label' => '2012-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der 2012-Heftromane je Autor:in.'],
            ['id' => 'lieblingsthemen', 'legacy_slug' => 'statistik-top10-lieblingsthemen', 'label' => 'TOP10 Lieblingsthemen', 'description' => 'Zeigt die beliebtesten Lieblingsthemen der Mitglieder.'],
        ];
    }

    public function up(): void
    {
        $defaultCost = (int) config('rewards.statistik_default_cost_baxx', 1);

        // Bestehende Statistik-Rewards per Slug indexieren (für Legacy-Matching)
        $existingBySlug = DB::table('rewards')
            ->where('category', 'Statistiken')
            ->get()
            ->keyBy('slug');

        $newSlugs = [];

        foreach ($this->getStatisticSections() as $sortOrder => $section) {
            $slug = 'statistik-'.$section['id'];
            $newSlugs[] = $slug;

            $metaData = [
                'title' => $section['label'],
                'description' => $section['description'],
                'category' => 'Statistiken',
                'is_active' => true,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ];

            // 1. Reward mit neuem Slug existiert bereits → Metadaten aktualisieren, cost_baxx bewahren
            if ($existingBySlug->has($slug)) {
                DB::table('rewards')->where('slug', $slug)->update($metaData);

                continue;
            }

            // 2. Legacy-Reward per explizitem Slug-Mapping finden → umbenennen (bewahrt reward_purchases)
            $legacySlug = $section['legacy_slug'];
            if ($existingBySlug->has($legacySlug)) {
                $legacy = $existingBySlug->get($legacySlug);
                DB::table('rewards')
                    ->where('id', $legacy->id)
                    ->update(array_merge(['slug' => $slug], $metaData));

                continue;
            }

            // 3. Weder Legacy noch neuer Slug → neu anlegen mit Default-Kosten
            DB::table('rewards')->insert(array_merge(
                ['slug' => $slug, 'cost_baxx' => $defaultCost, 'created_at' => now()],
                $metaData
            ));
        }

        // Verbleibende Legacy-Statistik-Rewards deaktivieren (falls Beschreibung nicht gematcht)
        DB::table('rewards')
            ->where('category', 'Statistiken')
            ->whereNotIn('slug', $newSlugs)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        $slugs = array_map(
            fn ($section) => 'statistik-'.$section['id'],
            $this->getStatisticSections()
        );

        // Neue Rewards nur deaktivieren statt löschen – bewahrt Kaufhistorie (cascadeOnDelete)
        DB::table('rewards')->whereIn('slug', $slugs)->update(['is_active' => false]);

        // Legacy-Statistik-Rewards reaktivieren (Gegenstück zu up())
        DB::table('rewards')
            ->where('category', 'Statistiken')
            ->whereNotIn('slug', $slugs)
            ->update(['is_active' => true]);
    }
};

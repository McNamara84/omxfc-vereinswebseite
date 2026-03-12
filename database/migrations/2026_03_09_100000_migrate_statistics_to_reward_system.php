<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Die 39 Statistik-Cards mit explizitem Legacy-Slug-Mapping und Legacy-Kosten.
     *
     * legacy_slug = Str::slug() des alten Titels aus config/rewards.php,
     * wie er vom RewardSeeder erzeugt wurde. Damit matchen wir bestehende
     * Rewards zuverlässig per Slug statt per Beschreibung.
     *
     * legacy_cost = Original-Kosten aus config/rewards.php (points-Feld),
     * damit neue Installationen ohne Legacy-Rewards korrekte Preise erhalten.
     */
    private function getStatisticSections(): array
    {
        return [
            ['id' => 'author-chart', 'legacy_slug' => 'statistik-maddrax-romane-je-autorin', 'legacy_cost' => 2, 'label' => 'Maddrax-Romane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl Maddrax-Romane je Autor:in.'],
            ['id' => 'teamplayer', 'legacy_slug' => 'statistik-top-teamplayer', 'legacy_cost' => 4, 'label' => 'Top Teamplayer', 'description' => 'Schaltet Statistik zu Autor:innen frei, die häufig gemeinsam mit anderen Autor:innen MADDRAX-Romane schreiben.'],
            ['id' => 'top-romane', 'legacy_slug' => 'statistik-top-maddrax-romane', 'legacy_cost' => 5, 'label' => 'Top 10 Maddrax-Romane', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Romanen im Maddraxikon frei.'],
            ['id' => 'top-autoren', 'legacy_slug' => 'statistik-top-maddrax-autorinnen', 'legacy_cost' => 7, 'label' => 'Top 10 Autor:innen nach Ø-Bewertung', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Autoren frei.'],
            ['id' => 'top-charaktere', 'legacy_slug' => 'statistik-top-maddrax-charaktere', 'legacy_cost' => 10, 'label' => 'Top 10 Charaktere nach Auftritten', 'description' => 'Schaltet die Statistik zu den am häufigsten auftretenden MADDRAX-Charakteren im Maddraxikon frei.'],
            ['id' => 'maddraxikon-bewertungen', 'legacy_slug' => 'statistik-bewertungen-im-maddraxikon', 'legacy_cost' => 11, 'label' => 'Bewertungen im Maddraxikon', 'description' => 'Zeigt Durchschnittsbewertung, Stimmenanzahl und Ø-Stimmen pro Roman.'],
            ['id' => 'mitglieds-rezensionen', 'legacy_slug' => 'statistik-rezensionen-unserer-mitglieder', 'legacy_cost' => 12, 'label' => 'Rezensionen unserer Mitglieder', 'description' => 'Zeigt Analysen rund um die Rezensionen im Verein.'],
            ['id' => 'zyklus-euree', 'legacy_slug' => 'statistik-bewertungen-des-euree-zyklus', 'legacy_cost' => 13, 'label' => 'Bewertungen des Euree-Zyklus', 'description' => 'Zeigt Bewertungen des Euree-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-meeraka', 'legacy_slug' => 'statistik-bewertungen-des-meeraka-zyklus', 'legacy_cost' => 14, 'label' => 'Bewertungen des Meeraka-Zyklus', 'description' => 'Zeigt Bewertungen des Meeraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-expedition', 'legacy_slug' => 'statistik-bewertungen-des-expeditions-zyklus', 'legacy_cost' => 15, 'label' => 'Bewertungen des Expeditions-Zyklus', 'description' => 'Zeigt Bewertungen des Expeditions-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-kratersee', 'legacy_slug' => 'statistik-bewertungen-des-kratersee-zyklus', 'legacy_cost' => 16, 'label' => 'Bewertungen des Kratersee-Zyklus', 'description' => 'Zeigt Bewertungen des Kratersee-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-daamuren', 'legacy_slug' => 'statistik-bewertungen-des-daamuren-zyklus', 'legacy_cost' => 17, 'label' => "Bewertungen des Daa'muren-Zyklus", 'description' => "Zeigt Bewertungen des Daa'muren-Zyklus aus dem Maddraxikon in einem Liniendiagramm."],
            ['id' => 'zyklus-wandler', 'legacy_slug' => 'statistik-bewertungen-des-wandler-zyklus', 'legacy_cost' => 18, 'label' => 'Bewertungen des Wandler-Zyklus', 'description' => 'Zeigt Bewertungen des Wandler-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-mars', 'legacy_slug' => 'statistik-bewertungen-des-mars-zyklus', 'legacy_cost' => 19, 'label' => 'Bewertungen des Mars-Zyklus', 'description' => 'Zeigt Bewertungen des Mars-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ausala', 'legacy_slug' => 'statistik-bewertungen-des-ausala-zyklus', 'legacy_cost' => 20, 'label' => 'Bewertungen des Ausala-Zyklus', 'description' => 'Zeigt Bewertungen des Ausala-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-afra', 'legacy_slug' => 'statistik-bewertungen-des-afra-zyklus', 'legacy_cost' => 21, 'label' => 'Bewertungen des Afra-Zyklus', 'description' => 'Zeigt Bewertungen des Afra-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-antarktis', 'legacy_slug' => 'statistik-bewertungen-des-antarktis-zyklus', 'legacy_cost' => 22, 'label' => 'Bewertungen des Antarktis-Zyklus', 'description' => 'Zeigt Bewertungen des Antarktis-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-schatten', 'legacy_slug' => 'statistik-bewertungen-des-schatten-zyklus', 'legacy_cost' => 23, 'label' => 'Bewertungen des Schatten-Zyklus', 'description' => 'Zeigt Bewertungen des Schatten-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ursprung', 'legacy_slug' => 'statistik-bewertungen-des-ursprung-zyklus', 'legacy_cost' => 24, 'label' => 'Bewertungen des Ursprung-Zyklus', 'description' => 'Zeigt Bewertungen des Ursprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-streiter', 'legacy_slug' => 'statistik-bewertungen-des-streiter-zyklus', 'legacy_cost' => 25, 'label' => 'Bewertungen des Streiter-Zyklus', 'description' => 'Zeigt Bewertungen des Streiter-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-archivar', 'legacy_slug' => 'statistik-bewertungen-des-archivar-zyklus', 'legacy_cost' => 26, 'label' => 'Bewertungen des Archivar-Zyklus', 'description' => 'Zeigt Bewertungen des Archivar-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-zeitsprung', 'legacy_slug' => 'statistik-bewertungen-des-zeitsprung-zyklus', 'legacy_cost' => 27, 'label' => 'Bewertungen des Zeitsprung-Zyklus', 'description' => 'Zeigt Bewertungen des Zeitsprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-fremdwelt', 'legacy_slug' => 'statistik-bewertungen-des-fremdwelt-zyklus', 'legacy_cost' => 28, 'label' => 'Bewertungen des Fremdwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Fremdwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-parallelwelt', 'legacy_slug' => 'statistik-bewertungen-des-parallelwelt-zyklus', 'legacy_cost' => 29, 'label' => 'Bewertungen des Parallelwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Parallelwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltenriss', 'legacy_slug' => 'statistik-bewertungen-des-weltenriss-zyklus', 'legacy_cost' => 30, 'label' => 'Bewertungen des Weltenriss-Zyklus', 'description' => 'Zeigt Bewertungen des Weltenriss-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-amraka', 'legacy_slug' => 'statistik-bewertungen-des-amraka-zyklus', 'legacy_cost' => 31, 'label' => 'Bewertungen des Amraka-Zyklus', 'description' => 'Zeigt Bewertungen des Amraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltrat', 'legacy_slug' => 'statistik-bewertungen-des-weltrat-zyklus', 'legacy_cost' => 32, 'label' => 'Bewertungen des Weltrat-Zyklus', 'description' => 'Zeigt Bewertungen des Weltrat-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-die-abenteurer-heftromane', 'legacy_cost' => 33, 'label' => 'Bewertungen der Die Abenteurer-Heftromane', 'description' => 'Zeigt Bewertungen der Die Abenteurer-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-autoren', 'legacy_slug' => 'statistik-die-abenteurer-heftromane-je-autorin', 'legacy_cost' => 34, 'label' => 'Die Abenteurer-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Die Abenteurer-Heftromane je Autor:in.'],
            ['id' => 'hardcover-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-hardcover', 'legacy_cost' => 40, 'label' => 'Bewertungen der Hardcover', 'description' => 'Zeigt Bewertungen der Hardcover aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'hardcover-autoren', 'legacy_slug' => 'statistik-maddrax-hardcover-je-autorin', 'legacy_cost' => 41, 'label' => 'Maddrax-Hardcover je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Maddrax-Hardcover je Autor:in.'],
            ['id' => 'top-themen', 'legacy_slug' => 'statistik-top20-maddrax-themen', 'legacy_cost' => 42, 'label' => 'TOP20 Maddrax-Themen', 'description' => 'Zeigt die 20 am besten bewerteten MADDRAX-Themen im Maddraxikon.'],
            ['id' => 'mission-mars-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-mission-mars-heftromane', 'legacy_cost' => 43, 'label' => 'Bewertungen der Mission Mars-Heftromane', 'description' => 'Zeigt Bewertungen der Mission Mars-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'mission-mars-autoren', 'legacy_slug' => 'statistik-mission-mars-heftromane-je-autorin', 'legacy_cost' => 44, 'label' => 'Mission Mars-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Mission Mars-Heftromane je Autor:in.'],
            ['id' => 'volk-der-tiefe-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-das-volk-der-tiefe-heftromane', 'legacy_cost' => 45, 'label' => 'Bewertungen der Das Volk der Tiefe-Heftromane', 'description' => 'Zeigt Bewertungen der Das Volk der Tiefe-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'volk-der-tiefe-autoren', 'legacy_slug' => 'statistik-das-volk-der-tiefe-heftromane-je-autorin', 'legacy_cost' => 46, 'label' => 'Das Volk der Tiefe-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Das Volk der Tiefe-Heftromane je Autor:in.'],
            ['id' => 'zweitausendzwoelf-bewertungen', 'legacy_slug' => 'statistik-bewertungen-der-2012-heftromane', 'legacy_cost' => 47, 'label' => 'Bewertungen der 2012-Heftromane', 'description' => 'Zeigt Bewertungen der 2012-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zweitausendzwoelf-autoren', 'legacy_slug' => 'statistik-2012-heftromane-je-autorin', 'legacy_cost' => 48, 'label' => '2012-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der 2012-Heftromane je Autor:in.'],
            ['id' => 'lieblingsthemen', 'legacy_slug' => 'statistik-top10-lieblingsthemen', 'legacy_cost' => 50, 'label' => 'TOP10 Lieblingsthemen', 'description' => 'Zeigt die beliebtesten Lieblingsthemen der Mitglieder.'],
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
            $legacySlug = $section['legacy_slug'];

            $metaData = [
                'title' => $section['label'],
                'description' => $section['description'],
                'category' => 'Statistiken',
                'is_active' => true,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ];

            $hasNewSlug = $existingBySlug->has($slug);
            $hasLegacySlug = $existingBySlug->has($legacySlug);

            // Fall A: Beide Slugs existieren → Käufe vom Legacy auf neuen Reward migrieren, Legacy deaktivieren
            if ($hasNewSlug && $hasLegacySlug) {
                $newReward = $existingBySlug->get($slug);
                $legacyReward = $existingBySlug->get($legacySlug);

                // Metadaten des neuen Rewards aktualisieren, cost_baxx bewahren
                DB::table('rewards')->where('id', $newReward->id)->update($metaData);

                // Käufe vom Legacy-Reward auf den neuen Reward umhängen (nur wenn nicht schon doppelt vorhanden)
                $existingBuyerIds = DB::table('reward_purchases')
                    ->where('reward_id', $newReward->id)
                    ->pluck('user_id');

                DB::table('reward_purchases')
                    ->where('reward_id', $legacyReward->id)
                    ->whereNotIn('user_id', $existingBuyerIds)
                    ->update(['reward_id' => $newReward->id]);

                // Legacy-Reward deaktivieren (nicht löschen – bewahrt Historie)
                DB::table('rewards')->where('id', $legacyReward->id)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

                continue;
            }

            // Fall B: Nur neuer Slug existiert → Metadaten aktualisieren, cost_baxx bewahren
            if ($hasNewSlug) {
                DB::table('rewards')->where('slug', $slug)->update($metaData);

                continue;
            }

            // Fall C: Nur Legacy-Slug existiert → umbenennen (bewahrt reward_purchases FK)
            if ($hasLegacySlug) {
                $legacy = $existingBySlug->get($legacySlug);
                DB::table('rewards')
                    ->where('id', $legacy->id)
                    ->update(array_merge(['slug' => $slug], $metaData));

                continue;
            }

            // Fall D: Weder Legacy noch neuer Slug → neu anlegen mit Legacy-Kosten
            DB::table('rewards')->insert(array_merge(
                ['slug' => $slug, 'cost_baxx' => $section['legacy_cost'], 'created_at' => now()],
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

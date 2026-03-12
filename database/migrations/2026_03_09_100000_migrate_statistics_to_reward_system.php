<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Die 39 Statistik-Cards und ihre Slugs.
     * Gleiche Reihenfolge wie $statisticSections im StatistikController.
     */
    private function getStatisticSections(): array
    {
        return [
            ['id' => 'author-chart', 'label' => 'Maddrax-Romane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl Maddrax-Romane je Autor:in.'],
            ['id' => 'teamplayer', 'label' => 'Top Teamplayer', 'description' => 'Schaltet Statistik zu Autor:innen frei, die häufig gemeinsam mit anderen Autor:innen MADDRAX-Romane schreiben.'],
            ['id' => 'top-romane', 'label' => 'Top 10 Maddrax-Romane', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Romanen im Maddraxikon frei.'],
            ['id' => 'top-autoren', 'label' => 'Top 10 Autor:innen nach Ø-Bewertung', 'description' => 'Schaltet die Statistik zu den am besten bewerteten MADDRAX-Autoren frei.'],
            ['id' => 'top-charaktere', 'label' => 'Top 10 Charaktere nach Auftritten', 'description' => 'Schaltet die Statistik zu den am häufigsten auftretenden MADDRAX-Charakteren im Maddraxikon frei.'],
            ['id' => 'maddraxikon-bewertungen', 'label' => 'Bewertungen im Maddraxikon', 'description' => 'Zeigt Durchschnittsbewertung, Stimmenanzahl und Ø-Stimmen pro Roman.'],
            ['id' => 'mitglieds-rezensionen', 'label' => 'Rezensionen unserer Mitglieder', 'description' => 'Zeigt Analysen rund um die Rezensionen im Verein.'],
            ['id' => 'zyklus-euree', 'label' => 'Bewertungen des Euree-Zyklus', 'description' => 'Zeigt Bewertungen des Euree-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-meeraka', 'label' => 'Bewertungen des Meeraka-Zyklus', 'description' => 'Zeigt Bewertungen des Meeraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-expedition', 'label' => 'Bewertungen des Expeditions-Zyklus', 'description' => 'Zeigt Bewertungen des Expeditions-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-kratersee', 'label' => 'Bewertungen des Kratersee-Zyklus', 'description' => 'Zeigt Bewertungen des Kratersee-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-daamuren', 'label' => "Bewertungen des Daa'muren-Zyklus", 'description' => "Zeigt Bewertungen des Daa'muren-Zyklus aus dem Maddraxikon in einem Liniendiagramm."],
            ['id' => 'zyklus-wandler', 'label' => 'Bewertungen des Wandler-Zyklus', 'description' => 'Zeigt Bewertungen des Wandler-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-mars', 'label' => 'Bewertungen des Mars-Zyklus', 'description' => 'Zeigt Bewertungen des Mars-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ausala', 'label' => 'Bewertungen des Ausala-Zyklus', 'description' => 'Zeigt Bewertungen des Ausala-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-afra', 'label' => 'Bewertungen des Afra-Zyklus', 'description' => 'Zeigt Bewertungen des Afra-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-antarktis', 'label' => 'Bewertungen des Antarktis-Zyklus', 'description' => 'Zeigt Bewertungen des Antarktis-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-schatten', 'label' => 'Bewertungen des Schatten-Zyklus', 'description' => 'Zeigt Bewertungen des Schatten-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-ursprung', 'label' => 'Bewertungen des Ursprung-Zyklus', 'description' => 'Zeigt Bewertungen des Ursprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-streiter', 'label' => 'Bewertungen des Streiter-Zyklus', 'description' => 'Zeigt Bewertungen des Streiter-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-archivar', 'label' => 'Bewertungen des Archivar-Zyklus', 'description' => 'Zeigt Bewertungen des Archivar-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-zeitsprung', 'label' => 'Bewertungen des Zeitsprung-Zyklus', 'description' => 'Zeigt Bewertungen des Zeitsprung-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-fremdwelt', 'label' => 'Bewertungen des Fremdwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Fremdwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-parallelwelt', 'label' => 'Bewertungen des Parallelwelt-Zyklus', 'description' => 'Zeigt Bewertungen des Parallelwelt-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltenriss', 'label' => 'Bewertungen des Weltenriss-Zyklus', 'description' => 'Zeigt Bewertungen des Weltenriss-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-amraka', 'label' => 'Bewertungen des Amraka-Zyklus', 'description' => 'Zeigt Bewertungen des Amraka-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zyklus-weltrat', 'label' => 'Bewertungen des Weltrat-Zyklus', 'description' => 'Zeigt Bewertungen des Weltrat-Zyklus aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-bewertungen', 'label' => 'Bewertungen der Die Abenteurer-Heftromane', 'description' => 'Zeigt Bewertungen der Die Abenteurer-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'abenteurer-autoren', 'label' => 'Die Abenteurer-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Die Abenteurer-Heftromane je Autor:in.'],
            ['id' => 'hardcover-bewertungen', 'label' => 'Bewertungen der Hardcover', 'description' => 'Zeigt Bewertungen der Hardcover aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'hardcover-autoren', 'label' => 'Maddrax-Hardcover je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Maddrax-Hardcover je Autor:in.'],
            ['id' => 'top-themen', 'label' => 'TOP20 Maddrax-Themen', 'description' => 'Zeigt die 20 am besten bewerteten MADDRAX-Themen im Maddraxikon.'],
            ['id' => 'mission-mars-bewertungen', 'label' => 'Bewertungen der Mission Mars-Heftromane', 'description' => 'Zeigt Bewertungen der Mission Mars-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'mission-mars-autoren', 'label' => 'Mission Mars-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Mission Mars-Heftromane je Autor:in.'],
            ['id' => 'volk-der-tiefe-bewertungen', 'label' => 'Bewertungen der Das Volk der Tiefe-Heftromane', 'description' => 'Zeigt Bewertungen der Das Volk der Tiefe-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'volk-der-tiefe-autoren', 'label' => 'Das Volk der Tiefe-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der Das Volk der Tiefe-Heftromane je Autor:in.'],
            ['id' => 'zweitausendzwoelf-bewertungen', 'label' => 'Bewertungen der 2012-Heftromane', 'description' => 'Zeigt Bewertungen der 2012-Heftromane aus dem Maddraxikon in einem Liniendiagramm.'],
            ['id' => 'zweitausendzwoelf-autoren', 'label' => '2012-Heftromane je Autor:in', 'description' => 'Balkendiagramm mit der Anzahl der 2012-Heftromane je Autor:in.'],
            ['id' => 'lieblingsthemen', 'label' => 'TOP10 Lieblingsthemen', 'description' => 'Zeigt die beliebtesten Lieblingsthemen der Mitglieder.'],
        ];
    }

    public function up(): void
    {
        $defaultCost = (int) config('rewards.statistik_default_cost_baxx', 1);

        // Bestehende Statistik-Rewards per Beschreibung indexieren (für Legacy-Matching)
        $existingByDescription = DB::table('rewards')
            ->where('category', 'Statistiken')
            ->get()
            ->keyBy('description');

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

            // 1. Reward mit neuem Slug existiert bereits → aktualisieren, cost_baxx bewahren
            if (DB::table('rewards')->where('slug', $slug)->exists()) {
                DB::table('rewards')->where('slug', $slug)->update($metaData);

                continue;
            }

            // 2. Legacy-Reward per Beschreibung finden → umbenennen (bewahrt reward_purchases)
            $legacy = $existingByDescription->get($section['description']);
            if ($legacy && $legacy->slug !== $slug) {
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

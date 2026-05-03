<?php

namespace Database\Seeders;

use App\Models\BaxxEarningRule;
use Illuminate\Database\Seeder;

class BaxxEarningRuleSeeder extends Seeder
{
    /**
     * Seed the Baxx earning rules based on current hardcoded values.
     * Safe to run multiple times (upserts by action_key).
     */
    public function run(): void
    {
        $rules = [
            [
                'action_key' => 'rezension',
                'label' => 'Rezension-Meilenstein',
                'description' => '1 Baxx für jede 10. Rezension eines Mitglieds.',
                'points' => 1,
                'every_count' => 10,
            ],
            [
                'action_key' => 'fanfiction_publish',
                'label' => 'Fanfiction veröffentlichen',
                'description' => 'Baxx für die Veröffentlichung einer Fanfiction.',
                'points' => 5,
                'every_count' => 1,
            ],
            [
                'action_key' => 'romantausch_offer',
                'label' => 'Romantausch-Angebot',
                'description' => '1 Baxx pro 10 neue Angebote in der Romantauschbörse.',
                'points' => 1,
                'every_count' => 10,
            ],
            [
                'action_key' => 'romantausch_request',
                'label' => 'Romantausch-Gesuch',
                'description' => 'Aktuell keine Baxx für neue Gesuche; kann im Adminbereich aktiviert werden.',
                'points' => 0,
                'every_count' => 1,
            ],
            [
                'action_key' => 'romantausch_swap_complete',
                'label' => 'Romantausch abschließen',
                'description' => '2 Baxx pro vollständig abgeschlossenem Tausch für jede beteiligte Seite.',
                'points' => 2,
                'every_count' => 1,
            ],
            [
                'action_key' => 'maddraxiversum_mission',
                'label' => 'Maddraxiversum-Mission',
                'description' => 'Standard-Baxx für eine abgeschlossene Maddraxiversum-Mission (kann pro Mission überschrieben werden).',
                'points' => 5,
                'every_count' => 1,
            ],
            [
                'action_key' => 'todo_complete',
                'label' => 'Aufgabe abschließen',
                'description' => 'Standard-Baxx für das Abschließen einer Aufgabe (kann pro Aufgabe überschrieben werden).',
                'points' => 1,
                'every_count' => 1,
            ],
        ];

        foreach ($rules as $rule) {
            BaxxEarningRule::updateOrCreate(
                ['action_key' => $rule['action_key']],
                $rule
            );
        }
    }
}

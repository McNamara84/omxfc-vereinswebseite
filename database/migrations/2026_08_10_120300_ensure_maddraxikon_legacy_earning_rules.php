<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('baxx_earning_rules')->insertOrIgnore([
            [
                'action_key' => 'maddraxikon_edit_session',
                'label' => 'Maddraxikon-Bearbeitungen',
                'description' => '1 Baxx für jede 5. qualifizierte Bearbeitungssitzung.',
                'points' => 1,
                'every_count' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'action_key' => 'maddraxikon_new_article',
                'label' => 'Neuer Maddraxikon-Artikel',
                'description' => '5 Baxx für einen qualifizierten neuen Maddraxikon-Artikel.',
                'points' => 5,
                'every_count' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // Existing installations may have owned these rows before this migration.
    }
};

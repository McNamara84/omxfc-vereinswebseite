<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureRollbackState();
        DB::table('rewards')->where('slug', 'bildersuche')->where('disabled_by_rollback', true)
            ->update(['is_active' => true, 'disabled_by_rollback' => false, 'updated_at' => now()]);

        if (! DB::table('rewards')->where('slug', 'bildersuche')->exists()) {
            DB::table('rewards')->insert([
                'slug' => 'bildersuche',
                'title' => 'Maddrax-Bildersuche',
                'description' => 'Finde Cover anhand ihrer Maddraxikon-Schlagworte. Mitglieder der AG Maddraxikon erhalten automatisch Zugang.',
                'category' => 'Bildersuche',
                'cost_baxx' => 100,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $this->ensureRollbackState();
        // Keep the reward, purchases and rollback marker. Repeated rollbacks
        // must not overwrite the distinction from an administrator deactivation.
        DB::table('rewards')->where('slug', 'bildersuche')->where('is_active', true)
            ->update(['is_active' => false, 'disabled_by_rollback' => true, 'updated_at' => now()]);
    }

    private function ensureRollbackState(): void
    {
        // Also handles installations that already ran the original migration.
        // Retain this column on rollback alongside the purchase history.
        if (! Schema::hasColumn('rewards', 'disabled_by_rollback')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->boolean('disabled_by_rollback')->default(false);
            });
        }
    }
};
